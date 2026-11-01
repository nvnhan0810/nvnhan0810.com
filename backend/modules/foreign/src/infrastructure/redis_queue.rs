//! Apalis Redis backend configuration and channel/priority naming.
use crate::config::QueueConfig;
pub use crate::config::{DispatchConfig, Priority};
use apalis_redis::{ConnectionManager, RedisStorage};
use app_resources::SharedRedis;
#[derive(Clone)]
pub struct Config {
    redis: SharedRedis,
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
    pub fn new(redis: SharedRedis) -> Result<Self, String> {
        Self::from_config(redis, QueueConfig::default())
    }
    pub fn from_config(redis: SharedRedis, config: QueueConfig) -> Result<Self, String> {
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
            return Err("Invalid Redis queue channel/prefix".into());
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
            redis,
            prefix,
            channels,
            dispatch,
            workers,
        })
    }
    pub async fn connect(&self) -> redis::RedisResult<ConnectionManager> {
        self.redis.connection().await
    }

    pub fn key(&self, channel: &str, priority: i16) -> String {
        let lane = match priority {
            0 => "high",
            5 => "normal",
            9 => "low",
            _ => "invalid",
        };
        format!(
            "{}:{}:{}:{}",
            self.redis.queue_prefix(),
            self.prefix,
            channel,
            lane
        )
    }
    pub fn storage<T>(
        &self,
        connection: ConnectionManager,
        channel: &str,
        priority: i16,
    ) -> RedisStorage<T> {
        RedisStorage::new(connection).with_config(
            apalis_redis::Config::default()
                .queue(self.key(channel, priority))
                .batch_size(1),
        )
    }
}
#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn environments_and_modules_use_separate_queue_names() {
        let dev = Config::new(SharedRedis::new("redis://127.0.0.1/", "dev").unwrap()).unwrap();
        let prod = Config::new(SharedRedis::new("redis://127.0.0.1/", "prod").unwrap()).unwrap();
        assert_eq!(
            dev.key("youtube-transcript", 5),
            "dev:foreign:queue:youtube-transcript:normal"
        );
        assert_ne!(
            dev.key("youtube-transcript", 5),
            prod.key("youtube-transcript", 5)
        );
        let other = Config::from_config(
            dev.redis.clone(),
            QueueConfig {
                prefix: "other:queue".into(),
                ..QueueConfig::default()
            },
        )
        .unwrap();
        assert_ne!(
            dev.key("youtube-transcript", 5),
            other.key("youtube-transcript", 5)
        );
    }
    #[test]
    fn validates_configuration_and_priority_names() {
        let redis = SharedRedis::new("redis://127.0.0.1:6379/4", "test").unwrap();
        let settings = QueueConfig {
            channels: vec!["youtube-transcript".into(), "youtube-bulk".into()],
            ..QueueConfig::default()
        };
        let config = Config::from_config(redis, settings).unwrap();
        assert_eq!(config.channels.len(), 2);
        assert_eq!(config.dispatch.automatic, Priority::Normal);
        assert_eq!(config.dispatch.manual, Priority::High);
        assert_ne!(
            config.key("youtube-transcript", 0),
            config.key("youtube-bulk", 0)
        );
        assert_ne!(
            config.key("youtube-transcript", 0),
            config.key("youtube-transcript", 5)
        );
        assert!(!valid_name("bad,channel"));
    }
}
