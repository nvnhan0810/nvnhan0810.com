//! Requires an empty disposable database with trust auth; no workers are started.
use apalis::prelude::*;
use app_resources::queue::{Job, storage};
use serde_json::{Value, json};
use sqlx::{PgPool, postgres::PgConnectOptions};
use std::str::FromStr;

#[tokio::test]
#[ignore = "requires empty QUEUE_RETRY_TEST_DATABASE_URL with trust auth"]
async fn retry_command_resets_all_failed_jobs_and_preserves_other_states() {
    let url = std::env::var("QUEUE_RETRY_TEST_DATABASE_URL").unwrap();
    let options = PgConnectOptions::from_str(&url).unwrap();
    let pool = PgPool::connect(&url).await.unwrap();
    apalis_postgres::PostgresStorage::setup(&pool)
        .await
        .unwrap();
    assert_eq!(
        sqlx::query_scalar::<_, i64>("SELECT count(*) FROM apalis.jobs")
            .fetch_one(&pool)
            .await
            .unwrap(),
        0,
        "Use an empty disposable test database"
    );
    for (label, kind, channel, status) in [
        ("transcript", "foreign.transcript", "default", "Failed"),
        ("alert", "telemetry.telegram-log", "reports", "Failed"),
        ("running", "test.job", "default", "Running"),
        ("done", "test.job", "default", "Done"),
        ("pending", "test.job", "default", "Pending"),
    ] {
        storage(&pool, channel)
            .push_task(
                TaskBuilder::new(
                    Job::new(kind, json!({"label":label,"id":"original-token"})).unwrap(),
                )
                .priority(7)
                .max_attempts(4)
                .idempotency_key(format!("retry-test-{label}"))
                .build(),
            )
            .await
            .unwrap();
        sqlx::query("UPDATE apalis.jobs SET status=$2,attempts=4,run_at=now()+interval '1 hour',lock_at=now(),done_at=now(),last_result=$3 WHERE convert_from(job,'UTF8')::jsonb#>>'{payload,label}'=$1")
            .bind(label).bind(status).bind(json!({"Err":"fixture failure"})).execute(&pool).await.unwrap();
    }
    let snapshot = |pool: PgPool| async move {
        sqlx::query_scalar::<_, Value>("SELECT to_jsonb(j) || jsonb_build_object('decoded',convert_from(job,'UTF8')::jsonb) FROM apalis.jobs j ORDER BY id")
            .fetch_all(&pool).await.unwrap()
    };
    let before = snapshot(pool.clone()).await;
    let output = tokio::process::Command::new(env!("CARGO_BIN_EXE_console"))
        .arg("queue:retry-failed")
        .current_dir(std::env::temp_dir())
        .env_clear()
        .env("DB_HOST", options.get_host())
        .env("DB_PORT", options.get_port().to_string())
        .env("DB_NAME", options.get_database().unwrap())
        .env("DB_USERNAME", options.get_username())
        .env("DB_PASSWORD", "")
        .output()
        .await
        .unwrap();
    assert!(
        output.status.success(),
        "{}",
        String::from_utf8_lossy(&output.stderr)
    );
    assert!(String::from_utf8_lossy(&output.stdout).contains("Requeued 2 failed jobs."));
    let after = snapshot(pool.clone()).await;
    assert_eq!(before.len(), after.len());
    for (old, new) in before.iter().zip(&after) {
        if old["status"] == "Failed" {
            assert_eq!(new["status"], "Pending");
            assert_eq!(new["attempts"], 0);
            for key in ["lock_at", "lock_by", "done_at", "last_result"] {
                assert!(new[key].is_null());
            }
            for key in [
                "id",
                "job",
                "decoded",
                "job_type",
                "priority",
                "max_attempts",
                "metadata",
                "idempotency_key",
            ] {
                assert_eq!(old[key], new[key], "Preserve {key}");
            }
        } else {
            assert_eq!(old, new, "Unrelated job state must not change");
        }
    }
    assert_eq!(
        sqlx::query_scalar::<_, i64>(
            "SELECT count(*) FROM apalis.jobs WHERE status='Pending' AND run_at<=now()"
        )
        .fetch_one(&pool)
        .await
        .unwrap(),
        2
    );
    assert_eq!(app_resources::queue::retry_failed(&pool).await.unwrap(), 0);
    pool.close().await;
}
