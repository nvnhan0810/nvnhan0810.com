//! PostgreSQL queue configuration shared by producers and the backend worker.
use crate::config::QueueConfig;
pub use crate::config::{DispatchConfig, Priority};
use apalis_postgres::PostgresStorage;
use sqlx::PgPool;
#[derive(Clone)]
pub struct Config {
    pub prefix: String,
    pub channels: Vec<String>,
    pub dispatch: DispatchConfig,
    pub workers: [usize; 3],
}
fn valid_name(value: &str) -> bool {
    !value.is_empty()
        && value.len() <= 100
        && value
            .bytes()
            .all(|c| c.is_ascii_alphanumeric() || matches!(c, b'-' | b'_' | b':' | b'.'))
}
impl Config {
    pub fn new() -> Result<Self, String> {
        Self::from_config(QueueConfig::default())
    }
    pub fn from_config(config: QueueConfig) -> Result<Self, String> {
        let QueueConfig {
            prefix,
            channels,
            dispatch,
            workers,
            ..
        } = config;
        let channel = &dispatch.channel;
        if !valid_name(channel)
            || !valid_name(&prefix)
            || channels.iter().any(|channel| !valid_name(channel))
        {
            return Err("Invalid PostgreSQL queue channel/prefix".into());
        }
        if channels
            .iter()
            .collect::<std::collections::HashSet<_>>()
            .len()
            != channels.len()
        {
            return Err("Duplicate transcript channels".into());
        }
        if workers.iter().sum::<usize>() == 0
            || workers.iter().any(|count| *count > 16)
            || channels.len() > 16
            || workers.iter().sum::<usize>() * channels.len() > 64
        {
            return Err("Invalid transcript worker counts (maximum 64 workers)".into());
        }
        Ok(Self {
            prefix,
            channels,
            dispatch,
            workers,
        })
    }
    pub fn key(&self, channel: &str, priority: i16) -> String {
        let lane = match priority {
            0 => "high",
            5 => "normal",
            9 => "low",
            _ => "invalid",
        };
        format!("{}:{}:{}", self.prefix, channel, lane)
    }
    pub fn storage<T>(&self, pool: &PgPool, channel: &str, priority: i16) -> PostgresStorage<T> {
        PostgresStorage::new(pool).with_config(
            apalis_postgres::Config::default()
                .queue(self.key(channel, priority))
                .batch_size(1),
        )
    }
}
