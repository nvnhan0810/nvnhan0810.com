//! Shared queue transport and handler registry. Modules declare extra channels explicitly.
use apalis::{
    layers::retry::{
        HasherRng, RetryPolicy,
        backoff::{ExponentialBackoffMaker, MakeBackoff},
    },
    prelude::*,
};
use serde::{Deserialize, Serialize};
use serde_json::Value;
use sqlx::PgPool;
use std::{
    collections::{BTreeMap, BTreeSet},
    future::Future,
    pin::Pin,
    sync::Arc,
    time::Duration,
};
use tokio::sync::watch;

pub const DEFAULT_CHANNEL: &str = "default";
pub const WORKERS_PER_CHANNEL: usize = 2;
#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct Job {
    pub kind: String,
    pub payload: Value,
}
impl Job {
    pub fn new(kind: &str, payload: impl Serialize) -> Result<Self, serde_json::Error> {
        Ok(Self {
            kind: kind.into(),
            payload: serde_json::to_value(payload)?,
        })
    }
}
pub fn key(channel: &str) -> String {
    format!("backend:queue:{channel}")
}
pub fn valid_channel(channel: &str) -> bool {
    !channel.is_empty()
        && channel.len() <= 100
        && channel
            .bytes()
            .all(|c| c.is_ascii_alphanumeric() || matches!(c, b'-' | b'_'))
}
pub fn channels(additional: impl IntoIterator<Item = String>) -> Result<Vec<String>, String> {
    let mut channels = BTreeSet::from([DEFAULT_CHANNEL.to_owned()]);
    for channel in additional {
        if !valid_channel(&channel) {
            return Err("Invalid queue channel".into());
        }
        channels.insert(channel);
    }
    if channels.len() > 16 {
        return Err("Maximum 16 queue channels".into());
    }
    Ok(channels.into_iter().collect())
}
pub fn storage(pool: &PgPool, channel: &str) -> apalis_postgres::PostgresStorage<Job> {
    apalis_postgres::PostgresStorage::new(pool).with_config(
        apalis_postgres::Config::default()
            .queue(key(channel))
            .batch_size(1),
    )
}
type HandlerFuture = Pin<Box<dyn Future<Output = Result<(), BoxDynError>> + Send>>;
type Handler = Arc<dyn Fn(Value, usize) -> HandlerFuture + Send + Sync>;
#[derive(Clone, Default)]
pub struct Registry {
    handlers: BTreeMap<String, Handler>,
}
impl Registry {
    pub fn register<F, Fut>(&mut self, kind: &str, handler: F) -> Result<(), String>
    where
        F: Fn(Value, usize) -> Fut + Send + Sync + 'static,
        Fut: Future<Output = Result<(), BoxDynError>> + Send + 'static,
    {
        if self.handlers.contains_key(kind) {
            return Err(format!("Duplicate queue job handler: {kind}"));
        }
        self.handlers.insert(
            kind.into(),
            Arc::new(move |payload, attempt| Box::pin(handler(payload, attempt))),
        );
        Ok(())
    }
    pub async fn execute(&self, job: Job, attempt: usize) -> Result<(), BoxDynError> {
        let handler = self
            .handlers
            .get(&job.kind)
            .ok_or_else(|| std::io::Error::other(format!("Unknown queue job: {}", job.kind)))?;
        handler(job.payload, attempt).await
    }
}
async fn handle(job: Job, registry: Data<Registry>, attempt: Attempt) -> Result<(), BoxDynError> {
    registry.execute(job, attempt.current()).await
}
async fn stopping(mut signal: watch::Receiver<bool>) -> Result<(), std::io::Error> {
    while !*signal.borrow() {
        if signal.changed().await.is_err() {
            break;
        }
    }
    Ok(())
}
pub async fn run(
    pool: PgPool,
    additional: Vec<String>,
    registry: Registry,
    signal: watch::Receiver<bool>,
) -> Result<(), String> {
    let channels = channels(additional)?;
    let (stop, worker_signal) = watch::channel(false);
    let mut workers = tokio::task::JoinSet::new();
    let instance = uuid::Uuid::new_v4();
    for channel in channels {
        for index in 0..WORKERS_PER_CHANNEL {
            let backoff = ExponentialBackoffMaker::new(
                Duration::from_secs(30),
                Duration::from_secs(60),
                0.0,
                HasherRng::default(),
            )
            .unwrap()
            .make_backoff();
            let worker = WorkerBuilder::new(format!("{}:{instance}:{index}", key(&channel)))
                .backend(storage(&pool, &channel))
                .data(registry.clone())
                .retry(RetryPolicy::retries(2).with_backoff(backoff))
                .build(handle);
            let signal = worker_signal.clone();
            workers.spawn(async move { worker.run_until(stopping(signal)).await });
        }
    }
    let result = tokio::select! {
        result = stopping(signal) => result.map_err(|e| e.to_string()),
        result = workers.join_next() => Err(format!("Queue consumer stopped unexpectedly: {result:?}")),
    };
    let _ = stop.send(true);
    let drained = tokio::time::timeout(Duration::from_secs(60), async {
        while let Some(worker) = workers.join_next().await {
            worker
                .map_err(|e| e.to_string())?
                .map_err(|e| e.to_string())?;
        }
        Ok::<_, String>(())
    })
    .await;
    workers.shutdown().await;
    result?;
    drained.map_err(|_| "Queue shutdown timed out".to_owned())?
}

/// Idempotent upgrade, run with the old workers stopped and drained.
/// Preserve IDs, attempts, scheduling, idempotency keys and job-specific payloads.
pub async fn migrate_legacy(pool: &PgPool) -> Result<(), sqlx::Error> {
    let old = vec![
        "foreign:queue:youtube-transcript:high",
        "foreign:queue:youtube-transcript:normal",
        "foreign:queue:youtube-transcript:low",
        "backend:queue:telegram-logs",
    ];
    let mut tx = pool.begin().await?;
    let running: bool = sqlx::query_scalar(
        "SELECT EXISTS(SELECT 1 FROM apalis.jobs WHERE job_type=ANY($1) AND status='Running')",
    )
    .bind(&old)
    .fetch_one(&mut *tx)
    .await?;
    if running {
        return Err(sqlx::Error::Protocol(
            "Drain the old queue workers before migrating running jobs to default".into(),
        ));
    }
    sqlx::query("UPDATE apalis.jobs SET job=convert_to(jsonb_build_object('kind',CASE WHEN job_type='backend:queue:telegram-logs' THEN 'telemetry.telegram-log' ELSE 'foreign.transcript' END,'payload',convert_from(job,'UTF8')::jsonb)::text,'UTF8'),priority=CASE WHEN job_type LIKE '%:high' THEN 9 WHEN job_type LIKE '%:low' THEN 0 ELSE 5 END,job_type=$2 WHERE job_type=ANY($1)")
        .bind(&old).bind(key(DEFAULT_CHANNEL)).execute(&mut *tx).await?;
    tx.commit().await
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn only_explicit_channels_are_added() {
        assert_eq!(channels([]).unwrap(), ["default"]);
        assert_eq!(
            channels(["images".into(), "default".into(), "images".into()]).unwrap(),
            ["default", "images"]
        );
        assert!(channels(["bad:name".into()]).is_err());
    }
    #[tokio::test]
    async fn different_jobs_share_one_transport_and_keep_their_handler() {
        let seen = Arc::new(std::sync::Mutex::new(Vec::new()));
        let mut registry = Registry::default();
        for kind in ["transcript", "telegram"] {
            let seen = seen.clone();
            registry
                .register(kind, move |payload, attempt| {
                    let seen = seen.clone();
                    async move {
                        seen.lock().unwrap().push((kind, payload, attempt));
                        Ok(())
                    }
                })
                .unwrap();
        }
        for kind in ["transcript", "telegram"] {
            let encoded =
                serde_json::to_vec(&Job::new(kind, serde_json::json!({"id":1})).unwrap()).unwrap();
            registry
                .execute(serde_json::from_slice(&encoded).unwrap(), 2)
                .await
                .unwrap();
        }
        let entries = seen.lock().unwrap();
        assert_eq!(entries[0].0, "transcript");
        assert_eq!(entries[1].0, "telegram");
        assert_eq!(entries[0].2, 2);
        drop(entries);
        assert!(
            registry
                .execute(Job::new("missing", ()).unwrap(), 1)
                .await
                .is_err()
        );
    }
}
