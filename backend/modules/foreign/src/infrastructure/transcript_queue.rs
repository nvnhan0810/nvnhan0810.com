//! Durable Apalis PostgreSQL dispatch and transcript handling.
use super::postgres_queue::{Config, Priority};
use apalis::layers::retry::{
    HasherRng, RetryPolicy,
    backoff::{ExponentialBackoffMaker, MakeBackoff},
};
use apalis::prelude::*;
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use sqlx::PgPool;
use std::time::Duration;
use uuid::Uuid;

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct TranscriptTask {
    pub id: Uuid,
    pub media_id: i64,
    pub video_id: String,
}
/// Persist the Apalis job and its media token in the same PostgreSQL transaction.
pub async fn enqueue(
    pool: &PgPool,
    config: &Config,
    media_id: i64,
    priority: Priority,
) -> Result<(), sqlx::Error> {
    let mut tx = pool.begin().await?;
    let mut payload: Value =
        sqlx::query_scalar("SELECT payload FROM foreign_media WHERE id=$1 FOR UPDATE")
            .bind(media_id)
            .fetch_one(&mut *tx)
            .await?;
    if matches!(
        payload["transcript_status"].as_str(),
        Some("queued" | "running")
    ) {
        return Ok(());
    }
    if !empty_transcript(&payload) {
        return Ok(());
    }
    let task = TranscriptTask {
        id: Uuid::new_v4(),
        media_id,
        video_id: payload["source_id"]
            .as_str()
            .ok_or_else(|| sqlx::Error::Protocol("Missing YouTube ID".into()))?
            .into(),
    };
    payload["transcript_task_id"] = json!(task.id);
    payload["transcript_status"] = json!("queued");
    payload["transcript_error"] = Value::Null;
    // The job and its media token commit together, using Apalis' transaction-aware sink.
    let encoded = serde_json::to_vec(&task).map_err(|e| sqlx::Error::Encode(Box::new(e)))?;
    apalis_postgres::queries::push_tasks(
        &mut *tx,
        &config.key(&config.dispatch.channel, priority as i16),
        vec![TaskBuilder::new(encoded).max_attempts(3).build()],
    )
    .await
    .map_err(|e| sqlx::Error::Protocol(format!("Could not queue captions: {e}")))?;
    sqlx::query("UPDATE foreign_media SET payload=$2 WHERE id=$1")
        .bind(media_id)
        .bind(payload)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    Ok(())
}
fn empty_transcript(payload: &Value) -> bool {
    payload["transcript"]
        .as_str()
        .unwrap_or("")
        .trim()
        .is_empty()
        && payload["transcript_segments"]
            .as_array()
            .is_none_or(Vec::is_empty)
}
fn current_task(payload: &Value, task: &TranscriptTask) -> bool {
    payload["transcript_task_id"].as_str() == Some(task.id.to_string().as_str())
        && payload["source_id"].as_str() == Some(task.video_id.as_str())
        && empty_transcript(payload)
}
/// Update media state only if no manual edit or newer task superseded this task.
pub async fn complete(
    pool: &PgPool,
    task: &TranscriptTask,
    segments: &[Value],
) -> Result<bool, sqlx::Error> {
    let mut tx = pool.begin().await?;
    let payload: Option<Value> =
        sqlx::query_scalar("SELECT payload FROM foreign_media WHERE id=$1 FOR UPDATE")
            .bind(task.media_id)
            .fetch_optional(&mut *tx)
            .await?;
    let Some(mut payload) = payload.filter(|payload| current_task(payload, task)) else {
        return Ok(false);
    };
    payload["transcript"] = json!(
        segments
            .iter()
            .filter_map(|line| line["text"].as_str())
            .collect::<Vec<_>>()
            .join("\n")
    );
    payload["transcript_segments"] = json!(segments);
    payload["transcript_status"] = json!("completed");
    payload["transcript_error"] = Value::Null;
    sqlx::query("UPDATE foreign_media SET payload=$2,updated_at=now() WHERE id=$1")
        .bind(task.media_id)
        .bind(payload)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    Ok(true)
}
pub async fn run_once_with<F, Fut>(
    pool: &PgPool,
    task: &TranscriptTask,
    attempt: usize,
    fetch: F,
) -> Result<bool, BoxDynError>
where
    F: FnOnce(String) -> Fut,
    Fut: std::future::Future<Output = Result<Vec<Value>, String>>,
{
    let mut tx = pool.begin().await?;
    let payload: Option<Value> =
        sqlx::query_scalar("SELECT payload FROM foreign_media WHERE id=$1 FOR UPDATE")
            .bind(task.media_id)
            .fetch_optional(&mut *tx)
            .await?;
    let Some(mut payload) = payload.filter(|payload| current_task(payload, task)) else {
        return Ok(false);
    };
    payload["transcript_status"] = json!("running");
    sqlx::query("UPDATE foreign_media SET payload=$2 WHERE id=$1")
        .bind(task.media_id)
        .bind(payload)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    let result = fetch(task.video_id.clone()).await.and_then(|segments| {
        if segments.is_empty() {
            Err("No subtitle text available. Add the transcript manually.".into())
        } else {
            Ok(segments)
        }
    });
    match result {
        Ok(segments) => {
            complete(pool, task, &segments).await?;
            Ok(true)
        }
        Err(error) => {
            sqlx::query("UPDATE foreign_media SET payload=payload || jsonb_build_object('transcript_status',$3::text,'transcript_error',$4::text) WHERE id=$1 AND payload->>'transcript_task_id'=$2")
                .bind(task.media_id).bind(task.id.to_string()).bind(if attempt >= 3 { "failed" } else { "queued" }).bind(error.chars().take(1000).collect::<String>()).execute(pool).await?;
            Err(std::io::Error::other(error).into())
        }
    }
}
async fn handle(
    task: TranscriptTask,
    pool: Data<PgPool>,
    attempt: Attempt,
) -> Result<(), BoxDynError> {
    run_once_with(&pool, &task, attempt.current(), |video| async move {
        super::captions::captions(&video).await
    })
    .await?;
    Ok(())
}
async fn stop(mut shutdown: tokio::sync::watch::Receiver<bool>) -> Result<(), std::io::Error> {
    while !*shutdown.borrow() {
        if shutdown.changed().await.is_err() {
            break;
        }
    }
    Ok(())
}
pub async fn run(pool: PgPool, config: Config, shutdown: tokio::sync::watch::Receiver<bool>) {
    let mut workers = tokio::task::JoinSet::new();
    let (worker_stop, worker_signal) = tokio::sync::watch::channel(false);
    let instance = Uuid::new_v4();
    for channel in &config.channels {
        for (lane, priority) in [Priority::High, Priority::Normal, Priority::Low]
            .iter()
            .enumerate()
        {
            for index in 0..config.workers[lane] {
                let storage = config.storage::<TranscriptTask>(&pool, channel, *priority as i16);
                let backoff = ExponentialBackoffMaker::new(
                    Duration::from_secs(30),
                    Duration::from_secs(60),
                    0.0,
                    HasherRng::default(),
                )
                .expect("static valid backoff")
                .make_backoff();
                let worker = WorkerBuilder::new(format!(
                    "{}:{instance}:{index}",
                    config.key(channel, *priority as i16)
                ))
                .backend(storage)
                .data(pool.clone())
                .retry(RetryPolicy::retries(2).with_backoff(backoff))
                .build(handle);
                let signal = worker_signal.clone();
                workers.spawn(async move { worker.run_until(stop(signal)).await });
            }
        }
    }
    tokio::select! {
        _ = stop(shutdown.clone()) => {},
        _ = workers.join_next() => tracing::error!("Apalis worker exited unexpectedly"),
    }
    // Signal remaining workers when shutdown is requested or a worker exits.
    let _ = worker_stop.send(true);
    if tokio::time::timeout(Duration::from_secs(60), async {
        while workers.join_next().await.is_some() {}
    })
    .await
    .is_err()
    {
        workers.shutdown().await;
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::sync::{
        Arc,
        atomic::{AtomicUsize, Ordering},
    };
    #[test]
    fn manual_edits_and_newer_tasks_reject_old_results() {
        let task = TranscriptTask {
            id: Uuid::new_v4(),
            media_id: 1,
            video_id: "video".into(),
        };
        let mut payload = json!({"transcript_task_id":task.id,"source_id":"video","transcript":"","transcript_segments":[]});
        assert!(current_task(&payload, &task));
        payload["transcript_task_id"] = json!(Uuid::new_v4());
        assert!(!current_task(&payload, &task));
        payload["transcript_task_id"] = json!(task.id);
        payload["transcript"] = json!("Manual edit");
        assert!(!current_task(&payload, &task));
        payload["transcript"] = json!("");
        payload["transcript_task_id"] = Value::Null;
        assert!(!current_task(&payload, &task));
    }
    async fn flaky(
        _task: TranscriptTask,
        calls: Data<Arc<AtomicUsize>>,
        worker: WorkerContext,
        attempt: Attempt,
    ) -> Result<(), BoxDynError> {
        let previous_calls = calls.fetch_add(1, Ordering::SeqCst);
        assert_eq!(attempt.current(), previous_calls + 1);
        if previous_calls < 2 {
            return Err(std::io::Error::other("temporary").into());
        }
        worker.stop()?;
        Ok(())
    }
    #[tokio::test]
    async fn apalis_retries_the_handler_until_success() {
        let mut storage = MemoryStorage::new();
        storage
            .push(TranscriptTask {
                id: Uuid::new_v4(),
                media_id: 1,
                video_id: "test".into(),
            })
            .await
            .unwrap();
        let calls = Arc::new(AtomicUsize::new(0));
        let worker = WorkerBuilder::new("transcript-retry-unit")
            .backend(storage)
            .data(calls.clone())
            .retry(RetryPolicy::retries(2))
            .build(flaky);
        tokio::time::timeout(Duration::from_secs(5), worker.run())
            .await
            .unwrap()
            .unwrap();
        assert_eq!(calls.load(Ordering::SeqCst), 3);
    }
}
