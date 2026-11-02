//! Requires an empty disposable PostgreSQL database, never a deployment database.
use apalis::prelude::*;
use foreign::infrastructure::{postgres_queue::Config, transcript_queue::TranscriptTask};
use sqlx::{PgPool, postgres::PgConnectOptions};
use std::{str::FromStr, time::Duration};
use tokio::process::{Child, Command};

fn worker(options: &PgConnectOptions) -> Child {
    Command::new(env!("CARGO_BIN_EXE_console"))
        .arg("queue:work")
        .current_dir(std::env::temp_dir())
        .env_clear()
        .env("DB_HOST", options.get_host())
        .env("DB_PORT", options.get_port().to_string())
        .env("DB_NAME", options.get_database().unwrap())
        .env("DB_USERNAME", options.get_username())
        .env("DB_PASSWORD", "")
        .env("TELEGRAM_LOG_ENABLED", "true")
        .env("TELEGRAM_BOT_TOKEN", "123:local-test")
        .env("TELEGRAM_CHAT_ID", "123")
        .kill_on_drop(true)
        .spawn()
        .unwrap()
}
async fn stop(child: &mut Child) {
    assert!(
        std::process::Command::new("/bin/kill")
            .args(["-TERM", &child.id().unwrap().to_string()])
            .status()
            .unwrap()
            .success()
    );
    assert!(
        tokio::time::timeout(Duration::from_secs(15), child.wait())
            .await
            .unwrap()
            .unwrap()
            .success()
    );
}
async fn wait_until(pool: &PgPool, query: &'static str) {
    tokio::time::timeout(Duration::from_secs(20), async {
        loop {
            if sqlx::query_scalar::<_, bool>(query)
                .fetch_one(pool)
                .await
                .unwrap()
            {
                return;
            }
            tokio::time::sleep(Duration::from_millis(100)).await;
        }
    })
    .await
    .unwrap();
}

#[tokio::test]
#[ignore = "requires empty QUEUE_TEST_DATABASE_URL with local trust auth"]
async fn shared_command_consumes_persisted_jobs_registers_all_handlers_and_stops_cleanly() {
    let url = std::env::var("QUEUE_TEST_DATABASE_URL").unwrap();
    let options = PgConnectOptions::from_str(&url).unwrap();
    let pool = PgPool::connect(&url).await.unwrap();
    console::migrations::apply(&pool, identity::MigrationAction::Up)
        .await
        .unwrap();
    // Applying the workspace migrations again must not collide with Apalis history.
    console::migrations::apply(&pool, identity::MigrationAction::Up)
        .await
        .unwrap();
    verify_atomic_dispatch(&pool).await;
    let config = Config::new().unwrap();
    let mut storage = config.storage::<TranscriptTask>(&pool, &config.dispatch.channel, 5);
    // A deleted media ID is intentionally safe to acknowledge without external fetching.
    let task = || TranscriptTask {
        id: uuid::Uuid::new_v4(),
        media_id: -1,
        video_id: "stale".into(),
    };
    storage.push(task()).await.unwrap();
    let mut child = worker(&options);
    wait_until(
        &pool,
        "SELECT count(*) = 1 FROM apalis.jobs WHERE status='Done'",
    )
    .await;
    wait_until(&pool, "SELECT count(*) >= 5 FROM apalis.workers").await;
    let telegram: bool = sqlx::query_scalar("SELECT EXISTS(SELECT 1 FROM apalis.workers WHERE worker_type='backend:queue:telegram-logs')")
        .fetch_one(&pool).await.unwrap();
    assert!(telegram, "the same process registers the Telegram consumer");
    stop(&mut child).await;
    storage.push(task()).await.unwrap();
    assert_eq!(
        sqlx::query_scalar::<_, i64>("SELECT count(*) FROM apalis.jobs WHERE status='Pending'")
            .fetch_one(&pool)
            .await
            .unwrap(),
        1
    );
    let mut child = worker(&options);
    wait_until(
        &pool,
        "SELECT count(*) = 2 FROM apalis.jobs WHERE status='Done'",
    )
    .await;
    stop(&mut child).await;
    pool.close().await;
}

async fn verify_atomic_dispatch(pool: &PgPool) {
    use foreign::infrastructure::{postgres_queue::Priority, transcript_queue::enqueue};
    let owner = uuid::Uuid::new_v4();
    sqlx::query("INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,'queue@example.test','Queue')")
        .bind(owner).bind(owner.to_string()).execute(pool).await.unwrap();
    let media_id: i64 = sqlx::query_scalar(r#"INSERT INTO foreign_media(user_id,payload) VALUES($1,'{"source_id":"stale"}'::jsonb) RETURNING id"#)
        .bind(owner).fetch_one(pool).await.unwrap();
    let config = Config::new().unwrap();
    // Fail after INSERTing the job, proving that the job is rolled back with media state.
    sqlx::raw_sql("CREATE FUNCTION fail_queue_media_update() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'fixture rollback'; END $$; CREATE TRIGGER queue_test_rollback BEFORE UPDATE ON foreign_media FOR EACH ROW EXECUTE FUNCTION fail_queue_media_update();")
        .execute(pool).await.unwrap();
    assert!(
        enqueue(pool, &config, media_id, Priority::Normal)
            .await
            .is_err()
    );
    assert_eq!(
        sqlx::query_scalar::<_, i64>("SELECT count(*) FROM apalis.jobs")
            .fetch_one(pool)
            .await
            .unwrap(),
        0
    );
    let token: Option<String> =
        sqlx::query_scalar("SELECT payload->>'transcript_task_id' FROM foreign_media WHERE id=$1")
            .bind(media_id)
            .fetch_one(pool)
            .await
            .unwrap();
    assert!(token.is_none());
    sqlx::raw_sql("DROP TRIGGER queue_test_rollback ON foreign_media; DROP FUNCTION fail_queue_media_update();")
        .execute(pool).await.unwrap();
    enqueue(pool, &config, media_id, Priority::Normal)
        .await
        .unwrap();
    enqueue(pool, &config, media_id, Priority::High)
        .await
        .unwrap();
    let jobs: Vec<(Vec<u8>, i32)> = sqlx::query_as("SELECT job,max_attempts FROM apalis.jobs")
        .fetch_all(pool)
        .await
        .unwrap();
    assert_eq!(jobs.len(), 1, "repeated dispatch must not duplicate work");
    let task: TranscriptTask = serde_json::from_slice(&jobs[0].0).unwrap();
    assert_eq!(task.media_id, media_id);
    assert_eq!(jobs[0].1, 3);
    sqlx::query("DELETE FROM apalis.jobs")
        .execute(pool)
        .await
        .unwrap();
    sqlx::query("DELETE FROM foreign_media WHERE id=$1")
        .bind(media_id)
        .execute(pool)
        .await
        .unwrap();
    sqlx::query("DELETE FROM identity_users WHERE id=$1")
        .bind(owner)
        .execute(pool)
        .await
        .unwrap();
}
