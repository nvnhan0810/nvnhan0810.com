//! Process-owned connections shared by application modules.
use std::{sync::Arc, time::Duration};
use tokio::sync::OnceCell;

pub use redis::aio::ConnectionManager;

/// Clones share one lazily initialized, reconnecting Redis connection manager.
#[derive(Clone)]
pub struct SharedRedis(Arc<RedisInner>);

struct RedisInner {
    client: redis::Client,
    queue_prefix: String,
    connection: OnceCell<ConnectionManager>,
}

impl SharedRedis {
    pub fn new(url: &str, queue_prefix: &str) -> redis::RedisResult<Self> {
        validate_queue_prefix(queue_prefix)?;
        Ok(Self(Arc::new(RedisInner {
            client: redis::Client::open(url)?,
            queue_prefix: queue_prefix.into(),
            connection: OnceCell::new(),
        })))
    }

    pub fn queue_prefix(&self) -> &str {
        &self.0.queue_prefix
    }

    pub async fn connection(&self) -> redis::RedisResult<ConnectionManager> {
        let connection = self
            .0
            .connection
            .get_or_try_init(|| async {
                tokio::time::timeout(
                    Duration::from_secs(5),
                    self.0.client.get_connection_manager(),
                )
                .await
                .map_err(|_| {
                    redis::RedisError::from((redis::ErrorKind::Io, "Redis connection timeout"))
                })?
            })
            .await?;
        Ok(connection.clone())
    }
}

fn validate_queue_prefix(prefix: &str) -> redis::RedisResult<()> {
    if prefix.is_empty()
        || prefix.len() > 100
        || !prefix
            .bytes()
            .all(|c| c.is_ascii_alphanumeric() || matches!(c, b'-' | b'_' | b':' | b'.'))
    {
        return Err(redis::RedisError::from((
            redis::ErrorKind::InvalidClientConfig,
            "Invalid REDIS_QUEUE_PREFIX",
        )));
    }
    Ok(())
}

/// The host closes the database only after all modules have stopped.
pub struct AppResources {
    pub database: sqlx::PgPool,
    pub redis: SharedRedis,
}

impl AppResources {
    pub async fn connect(
        database: sqlx::postgres::PgConnectOptions,
        redis_url: &str,
        queue_prefix: &str,
    ) -> Result<Self, Box<dyn std::error::Error>> {
        let redis = SharedRedis::new(redis_url, queue_prefix)?;
        Self::with_redis(database, redis).await
    }

    pub async fn with_redis(
        database: sqlx::postgres::PgConnectOptions,
        redis: SharedRedis,
    ) -> Result<Self, Box<dyn std::error::Error>> {
        let database = sqlx::postgres::PgPoolOptions::new()
            .max_connections(10)
            .acquire_timeout(Duration::from_secs(10))
            .connect_with(database)
            .await?;
        Ok(Self { database, redis })
    }

    pub async fn shutdown(self) {
        self.database.close().await;
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn rejects_missing_or_malformed_environment_prefix() {
        for prefix in ["", "dev prod", "dev/queue", " dev"] {
            assert!(SharedRedis::new("redis://127.0.0.1/", prefix).is_err());
        }
    }

    #[test]
    fn redis_clones_share_the_connection_cell_without_connecting() {
        let redis = SharedRedis::new("redis://127.0.0.1:6379/0", "test").unwrap();
        let other = redis.clone();
        assert!(Arc::ptr_eq(&redis.0, &other.0));
        assert!(redis.0.connection.get().is_none());
    }
}
