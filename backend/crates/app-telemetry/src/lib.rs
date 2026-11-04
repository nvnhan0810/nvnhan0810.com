//! Nonblocking warning/error capture -> Apalis PostgreSQL -> Telegram.
use apalis::prelude::*;
use app_config::ConfigSource;
use serde::{Deserialize, Serialize};
use sqlx::PgPool;
use std::{
    collections::HashMap,
    fmt::Write,
    sync::{
        Arc,
        atomic::{AtomicUsize, Ordering},
    },
    time::{Duration, Instant},
};
use tokio::{
    sync::{mpsc, watch},
    task::JoinHandle,
};
use tracing::{
    Event, Level, Subscriber,
    field::{Field, Visit},
};
use tracing_subscriber::{Layer, layer::Context, prelude::*};

const CAPACITY: usize = 1024;
const MAX_TEXT: usize = 3800;
const DEDUPE: Duration = Duration::from_secs(60);

#[derive(Clone, Serialize, Deserialize)]
struct TelegramLog {
    text: String,
}

struct Settings {
    token: String,
    chat_id: String,
    environment: String,
    minimum: Level,
    secrets: Vec<String>,
}
impl Settings {
    fn load(source: &ConfigSource) -> Result<Option<Self>, String> {
        match source.optional("TELEGRAM_LOG_ENABLED").unwrap_or("false") {
            "false" | "0" => return Ok(None),
            "true" | "1" => {}
            _ => return Err("TELEGRAM_LOG_ENABLED must be true or false".into()),
        }
        let minimum = match source.optional("TELEGRAM_LOG_LEVEL").unwrap_or("warning") {
            "warning" | "warn" => Level::WARN,
            "error" => Level::ERROR,
            _ => return Err("TELEGRAM_LOG_LEVEL must be warning or error".into()),
        };
        let token = source.required("TELEGRAM_BOT_TOKEN")?;
        if !token
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || matches!(b, b':' | b'_' | b'-'))
        {
            return Err("Invalid TELEGRAM_BOT_TOKEN".into());
        }
        Ok(Some(Self {
            token,
            chat_id: source.required("TELEGRAM_CHAT_ID")?,
            environment: source.optional("APP_ENV").unwrap_or("unknown").into(),
            minimum,
            secrets: ["TELEGRAM_BOT_TOKEN", "DB_PASSWORD", "GOOGLE_CLIENT_SECRET"]
                .iter()
                .filter_map(|key| source.optional(key))
                .filter(|value| !value.is_empty())
                .map(str::to_owned)
                .collect(),
        }))
    }
    fn redact(&self, mut text: String) -> String {
        for secret in &self.secrets {
            text = text.replace(secret, "[REDACTED]");
        }
        text.chars().take(MAX_TEXT).collect()
    }
}

struct Fields(String);
impl Visit for Fields {
    fn record_debug(&mut self, field: &Field, value: &dyn std::fmt::Debug) {
        let name = field.name();
        let sensitive = [
            "password",
            "token",
            "secret",
            "authorization",
            "cookie",
            "body",
            "headers",
            "url",
        ]
        .iter()
        .any(|part| name.to_ascii_lowercase().contains(part));
        let value = if sensitive {
            "[REDACTED]".into()
        } else {
            format!("{value:?}")
                .chars()
                .take(MAX_TEXT)
                .collect::<String>()
        };
        if self.0.len() < MAX_TEXT * 4 {
            let _ = writeln!(self.0, "{name}: {value}");
        }
    }
}
struct TelegramLayer {
    sender: mpsc::Sender<TelegramLog>,
    settings: Arc<Settings>,
    dropped: Arc<AtomicUsize>,
}
fn eligible(level: &Level, target: &str, minimum: Level) -> bool {
    *level <= minimum && !target.starts_with("app_telemetry") && !target.starts_with("apalis")
}
impl<S: Subscriber> Layer<S> for TelegramLayer {
    fn on_event(&self, event: &Event<'_>, _: Context<'_, S>) {
        let meta = event.metadata();
        if !eligible(meta.level(), meta.target(), self.settings.minimum) {
            return;
        }
        let mut fields = Fields(String::new());
        event.record(&mut fields);
        let text = self.settings.redact(format!(
            "{} — backend ({})\n{}\n{}",
            meta.level(),
            self.settings.environment,
            meta.target(),
            fields.0
        ));
        if self.sender.try_send(TelegramLog { text }).is_err() {
            self.dropped.fetch_add(1, Ordering::Relaxed);
        }
    }
}

pub struct Telemetry {
    shutdown: watch::Sender<bool>,
    tasks: Vec<JoinHandle<()>>,
}
impl Telemetry {
    /// Install stderr logging and optional Telegram capture. No network I/O here.
    pub fn init(source: &ConfigSource, pool: PgPool) -> Result<Self, Box<dyn std::error::Error>> {
        let settings = Settings::load(source)?.map(Arc::new);
        let (sender, receiver) = mpsc::channel(CAPACITY);
        let dropped = Arc::new(AtomicUsize::new(0));
        let layer = settings.as_ref().map(|settings| TelegramLayer {
            sender,
            settings: settings.clone(),
            dropped: dropped.clone(),
        });
        tracing_subscriber::registry()
            .with(
                tracing_subscriber::fmt::layer()
                    .with_ansi(false)
                    .with_writer(std::io::stderr)
                    .with_filter(tracing_subscriber::filter::LevelFilter::INFO),
            )
            .with(layer)
            .try_init()?;
        let (shutdown, signal) = watch::channel(false);
        let tasks = if settings.is_some() {
            let publisher = tokio::spawn(publish(pool, receiver, signal, dropped));
            let old_hook = std::panic::take_hook();
            std::panic::set_hook(Box::new(move |info| {
                let message = info
                    .payload()
                    .downcast_ref::<String>()
                    .map(String::as_str)
                    .or_else(|| info.payload().downcast_ref::<&str>().copied())
                    .unwrap_or("panic");
                tracing::error!(target: "backend::panic", message, location = ?info.location(), "Backend panic");
                old_hook(info);
            }));
            vec![publisher]
        } else {
            Vec::new()
        };
        Ok(Self { shutdown, tasks })
    }
    pub async fn shutdown(self) {
        let _ = self.shutdown.send(true);
        // Drain the local buffer into PostgreSQL; persisted jobs survive worker shutdown.
        for mut task in self.tasks {
            if tokio::time::timeout(Duration::from_secs(15), &mut task)
                .await
                .is_err()
            {
                task.abort();
                let _ = task.await;
                eprintln!("Telegram logging shutdown timed out; buffered logs may be lost");
            }
        }
    }
}
pub const CHANNEL: &str = app_resources::queue::DEFAULT_CHANNEL;
fn storage(pool: &PgPool) -> apalis_postgres::PostgresStorage<app_resources::queue::Job> {
    app_resources::queue::storage(pool, CHANNEL)
}
async fn publish(
    pool: PgPool,
    mut receiver: mpsc::Receiver<TelegramLog>,
    mut stop: watch::Receiver<bool>,
    dropped: Arc<AtomicUsize>,
) {
    let mut seen = HashMap::<String, Instant>::new();
    loop {
        let log = if *stop.borrow() {
            receiver.close();
            receiver.recv().await
        } else {
            tokio::select! { _ = stop.changed() => continue, log = receiver.recv() => log }
        };
        let Some(log) = log else {
            break;
        };
        let lost = dropped.swap(0, Ordering::Relaxed);
        if lost > 0 {
            eprintln!("Telegram logging buffer overflow: {lost} events dropped");
        }
        seen.retain(|_, at| at.elapsed() < DEDUPE);
        if seen.contains_key(&log.text) {
            continue;
        }
        let dispatch_id = uuid::Uuid::new_v4().to_string();
        loop {
            let result = async {
                storage(&pool)
                    .push_task(
                        TaskBuilder::new(app_resources::queue::Job::new(
                            "telemetry.telegram-log",
                            &log,
                        )?)
                        .priority(5)
                        .idempotency_key(dispatch_id.clone())
                        .max_attempts(3)
                        .build(),
                    )
                    .await?;
                Ok::<_, BoxDynError>(())
            }
            .await;
            if result.is_ok() {
                break;
            }
            eprintln!("Telegram log queue unavailable; retrying in background");
            if *stop.borrow() {
                eprintln!("Telegram log not persisted during shutdown");
                return;
            }
            tokio::select! { _ = stop.changed() => {}, _ = tokio::time::sleep(Duration::from_secs(2)) => {} }
        }
        if seen.len() >= CAPACITY {
            seen.clear();
        }
        seen.insert(log.text, Instant::now());
    }
}
#[derive(Clone)]
struct Sender {
    client: reqwest::Client,
    endpoint: String,
    chat_id: String,
}
impl Sender {
    async fn send(&self, log: &TelegramLog) -> Result<(), BoxDynError> {
        let response = self.client.post(&self.endpoint).json(&serde_json::json!({
            "chat_id": self.chat_id, "text": log.text, "link_preview_options": {"is_disabled": true}
        })).send().await.map_err(|_| std::io::Error::other("Telegram network error"))?;
        let success = response.status().is_success();
        let body: serde_json::Value = response
            .json()
            .await
            .map_err(|_| std::io::Error::other("Invalid Telegram response"))?;
        if success && body["ok"] == true {
            return Ok(());
        }
        if let Some(seconds) = body["parameters"]["retry_after"].as_u64() {
            tokio::time::sleep(Duration::from_secs(seconds.min(60))).await;
        }
        eprintln!("Telegram delivery failed; Apalis will apply its retry policy");
        Err(std::io::Error::other("Telegram delivery failed").into())
    }
}
/// Register delivery with the shared consumer; never start a competing typed worker.
pub fn register_jobs(
    source: &ConfigSource,
    registry: &mut app_resources::queue::Registry,
) -> Result<(), String> {
    let settings = Settings::load(source)?;
    let sender = settings.map(|settings| Sender {
        client: reqwest::Client::builder()
            .timeout(Duration::from_secs(5))
            .redirect(reqwest::redirect::Policy::none())
            .build()
            .expect("static HTTP client config"),
        endpoint: format!("https://api.telegram.org/bot{}/sendMessage", settings.token),
        chat_id: settings.chat_id,
    });
    registry.register("telemetry.telegram-log", move |payload, _attempt| {
        let sender = sender.clone();
        async move {
            let log: TelegramLog = serde_json::from_value(payload)?;
            // Keep persisted alerts retryable when delivery is temporarily disabled.
            let sender =
                sender.ok_or_else(|| std::io::Error::other("Telegram delivery is disabled"))?;
            sender.send(&log).await
        }
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    fn settings() -> Arc<Settings> {
        Arc::new(
            Settings::load(&ConfigSource::from_values([
                ("TELEGRAM_LOG_ENABLED".into(), "true".into()),
                ("TELEGRAM_BOT_TOKEN".into(), "123:test-token".into()),
                ("TELEGRAM_CHAT_ID".into(), "-100123".into()),
                ("DB_PASSWORD".into(), "database-password".into()),
            ]))
            .unwrap()
            .unwrap(),
        )
    }
    #[test]
    fn disabled_requires_no_credentials_and_enabled_validates_them() {
        assert!(
            Settings::load(&ConfigSource::from_values([]))
                .unwrap()
                .is_none()
        );
        assert!(
            Settings::load(&ConfigSource::from_values([(
                "TELEGRAM_LOG_ENABLED".into(),
                "true".into()
            )]))
            .is_err()
        );
    }
    #[test]
    fn captures_only_warning_errors_redacts_secrets_and_does_not_wait_when_full() {
        let (sender, mut receiver) = mpsc::channel(1);
        let dropped = Arc::new(AtomicUsize::new(0));
        let subscriber = tracing_subscriber::registry().with(TelegramLayer {
            sender,
            settings: settings(),
            dropped: dropped.clone(),
        });
        tracing::subscriber::with_default(subscriber, || {
            tracing::info!(target: "backend::test", "ignored");
            tracing::warn!(target: "apalis::worker", "ignored transport warning");
            tracing::warn!(
                target: "backend::test",
                authorization = "Bearer hidden",
                message = "database-password 123:test-token"
            );
            tracing::error!(target: "backend::test", "overflow");
        });
        let log = receiver.try_recv().unwrap();
        assert!(!log.text.contains("database-password"));
        assert!(!log.text.contains("123:test-token"));
        assert!(!log.text.contains("Bearer hidden"));
        assert!(log.text.contains("[REDACTED]"));
        assert_eq!(dropped.load(Ordering::Relaxed), 1);
        assert!(receiver.try_recv().is_err());
    }
    #[test]
    fn truncates_unicode_and_supports_error_only() {
        let settings = settings();
        assert_eq!(settings.redact("😀".repeat(5000)).chars().count(), MAX_TEXT);
        assert!(!eligible(&Level::WARN, "foreign", Level::ERROR));
        assert!(eligible(&Level::ERROR, "foreign", Level::ERROR));
        assert!(!eligible(
            &Level::ERROR,
            "app_telemetry::worker",
            Level::WARN
        ));
    }
    #[tokio::test]
    async fn delivery_checks_telegram_body_and_http_status() {
        use axum::{Json, Router, http::StatusCode, routing::post};
        let app = Router::new()
            .route(
                "/ok",
                post(|Json(body): Json<serde_json::Value>| async move {
                    assert_eq!(body["chat_id"], "-100123");
                    assert_eq!(body["text"], "test alert");
                    Json(serde_json::json!({"ok":true}))
                }),
            )
            .route(
                "/rejected",
                post(|| async { Json(serde_json::json!({"ok":false})) }),
            )
            .route(
                "/failed",
                post(|| async {
                    (
                        StatusCode::INTERNAL_SERVER_ERROR,
                        Json(serde_json::json!({"ok":false})),
                    )
                }),
            );
        let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
        let address = listener.local_addr().unwrap();
        let server = tokio::spawn(async move { axum::serve(listener, app).await.unwrap() });
        let log = TelegramLog {
            text: "test alert".into(),
        };
        for (path, succeeds) in [("ok", true), ("rejected", false), ("failed", false)] {
            let sender = Sender {
                client: reqwest::Client::builder().no_proxy().build().unwrap(),
                endpoint: format!("http://{address}/{path}"),
                chat_id: "-100123".into(),
            };
            assert_eq!(sender.send(&log).await.is_ok(), succeeds);
        }
        server.abort();
    }
}
