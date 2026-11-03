use crate::infrastructure as repo;
use serde_json::json;
use sqlx::PgPool;
use tokio::sync::watch;
use web_push::{
    ContentEncoding, HyperWebPushClient, SubscriptionInfo, VapidSignatureBuilder, WebPushClient,
    WebPushMessageBuilder,
};
#[derive(Clone, Default)]
pub struct PushConfig {
    pub public_key: String,
    pub private_key: String,
    pub subject: String,
    pub origin: String,
}
impl PushConfig {
    pub fn from_source(source: &app_config::ConfigSource) -> Self {
        Self {
            public_key: source
                .optional("TODO_VAPID_PUBLIC_KEY")
                .unwrap_or("")
                .into(),
            private_key: source
                .optional("TODO_VAPID_PRIVATE_KEY")
                .unwrap_or("")
                .into(),
            subject: source
                .optional("TODO_VAPID_SUBJECT")
                .unwrap_or("mailto:nguyenvannhan0810@gmail.com")
                .into(),
            origin: source
                .optional("TODO_WEB_ORIGIN")
                .unwrap_or("http://localhost:5177")
                .trim_end_matches('/')
                .into(),
        }
    }
    pub fn configured(&self) -> bool {
        !self.public_key.is_empty() && !self.private_key.is_empty()
    }
}
pub async fn run(
    pool: PgPool,
    config: PushConfig,
    mut shutdown: watch::Receiver<bool>,
) -> Result<(), String> {
    let mut interval = tokio::time::interval(std::time::Duration::from_secs(1));
    loop {
        tokio::select! {_ = shutdown.changed()=>return Ok(()),_ = interval.tick()=>{
         if let Err(e)=tick(&pool,&config).await{tracing::error!(error=?e,"Todos background processing failed");}
        }}
    }
}
pub async fn tick(pool: &PgPool, config: &PushConfig) -> crate::application::Result<()> {
    let owners: Vec<uuid::Uuid> = sqlx::query_scalar(
        "SELECT user_id FROM todo_pomodoro_states WHERE is_running AND ends_at<=$1 LIMIT 100",
    )
    .bind(chrono::Utc::now().timestamp_millis())
    .fetch_all(pool)
    .await?;
    for owner in owners {
        let mut tx = pool.begin().await?;
        repo::lock(&mut tx, owner).await?;
        let mut state = repo::pomodoro(&mut tx, owner).await?;
        repo::reconcile(&mut tx, &mut state, chrono::Utc::now().timestamp_millis()).await?;
        tx.commit().await?;
    }
    for _ in 0..20 {
        if !deliver(pool, config).await? {
            break;
        }
    }
    Ok(())
}
async fn deliver(pool: &PgPool, config: &PushConfig) -> crate::application::Result<bool> {
    let mut tx = pool.begin().await?;
    let event:Option<(i64,uuid::Uuid,uuid::Uuid,String,String,i32,bool)>=sqlx::query_as("SELECT e.id,e.user_id,e.session_uuid,e.from_phase,e.to_phase,e.attempts,(e.created_at<now()-interval '5 minutes' OR COALESCE(p.last_focused_at>now()-interval '15 seconds',false)) FROM todo_push_events e LEFT JOIN todo_pomodoro_states p ON p.user_id=e.user_id WHERE e.delivered_at IS NULL AND e.available_at<=now() ORDER BY e.id LIMIT 1 FOR UPDATE OF e SKIP LOCKED").fetch_optional(&mut *tx).await?;
    let Some((id, owner, session, from, to, attempts, skip)) = event else {
        return Ok(false);
    };
    let mut failed = false;
    if config.configured() && !skip {
        let subscriptions:Vec<(i64,String,String,String)>=sqlx::query_as("SELECT s.id,s.endpoint,s.public_key,s.auth_token FROM web_push_subscriptions s WHERE s.user_id=$1 AND s.is_active AND NOT EXISTS(SELECT 1 FROM todo_push_deliveries d WHERE d.event_id=$2 AND d.subscription_id=s.id)").bind(owner).bind(id).fetch_all(&mut *tx).await?;
        let client = HyperWebPushClient::new();
        for (subscription_id, endpoint, key, auth) in subscriptions {
            let info = SubscriptionInfo::new(endpoint, key, auth);
            let payload=json!({"title":if from=="focus"{"Hết phiên tập trung"}else{"Hết giờ nghỉ"},"body":if to=="focus"{"Bắt đầu phiên tập trung tiếp theo."}else{"Đến giờ nghỉ ngơi."},"url":format!("{}/matrix",config.origin),"tag":"todo-pomodoro-phase","icon":format!("{}/images/todo-icon.png",config.origin),"topic":session.simple().to_string()}).to_string();
            let message = (|| {
                let mut sig = VapidSignatureBuilder::from_base64(&config.private_key, &info)?;
                sig.add_claim("sub", config.subject.clone());
                let mut b = WebPushMessageBuilder::new(&info);
                b.set_payload(ContentEncoding::Aes128Gcm, payload.as_bytes());
                b.set_vapid_signature(sig.build()?);
                b.set_ttl(300);
                b.build()
            })();
            let result = match message {
                Ok(message) => {
                    tokio::time::timeout(std::time::Duration::from_secs(10), client.send(message))
                        .await
                        .map_err(|_| "timeout".to_owned())
                        .and_then(|r| r.map_err(|e| e.to_string()))
                }
                Err(e) => Err(e.to_string()),
            };
            match result {
                Ok(()) => {
                    sqlx::query("INSERT INTO todo_push_deliveries(event_id,subscription_id) VALUES($1,$2) ON CONFLICT DO NOTHING").bind(id).bind(subscription_id).execute(&mut *tx).await?;
                    sqlx::query("UPDATE web_push_subscriptions SET failed_sent=0,last_failed_reason=NULL,updated_at=now() WHERE id=$1").bind(subscription_id).execute(&mut *tx).await?;
                }
                Err(reason) => {
                    failed = true;
                    sqlx::query("UPDATE web_push_subscriptions SET failed_sent=failed_sent+1,last_failed_reason=$2,is_active=(failed_sent+1<5),updated_at=now() WHERE id=$1").bind(subscription_id).bind(reason.chars().take(500).collect::<String>()).execute(&mut *tx).await?;
                }
            }
        }
    }
    if failed && attempts < 4 {
        sqlx::query("UPDATE todo_push_events SET attempts=attempts+1,available_at=now()+make_interval(secs=>$2) WHERE id=$1").bind(id).bind(5.0*2_f64.powi(attempts)).execute(&mut *tx).await?;
    } else {
        sqlx::query(
            "UPDATE todo_push_events SET delivered_at=now(),attempts=attempts+1 WHERE id=$1",
        )
        .bind(id)
        .execute(&mut *tx)
        .await?;
    }
    tx.commit().await?;
    Ok(true)
}
