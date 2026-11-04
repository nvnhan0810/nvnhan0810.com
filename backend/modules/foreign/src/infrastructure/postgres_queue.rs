//! Module dispatch settings. Consumers are owned by the shared queue worker.
use crate::config::QueueConfig;
pub use crate::config::{DispatchConfig, Priority};
#[derive(Clone)]
pub struct Config {
    pub channels: Vec<String>,
    pub dispatch: DispatchConfig,
}
impl Config {
    pub fn new() -> Result<Self, String> {
        Self::from_config(QueueConfig::default())
    }
    pub fn from_config(config: QueueConfig) -> Result<Self, String> {
        let channels = app_resources::queue::channels(config.channels.clone())?;
        if !channels.contains(&config.dispatch.channel) {
            return Err(
                "Dispatch channel must be default or explicitly declared by the module".into(),
            );
        }
        Ok(Self {
            channels: config.channels,
            dispatch: config.dispatch,
        })
    }
    pub fn key(&self, channel: &str) -> String {
        app_resources::queue::key(channel)
    }
}
impl Priority {
    /// Apalis uses larger values for higher priority in the same queue.
    pub fn queue_priority(self) -> usize {
        match self {
            Self::High => 9,
            Self::Normal => 5,
            Self::Low => 0,
        }
    }
}
#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn default_dispatch_does_not_create_module_channels_or_priority_lanes() {
        let config = Config::new().unwrap();
        assert!(config.channels.is_empty());
        assert_eq!(config.dispatch.channel, "default");
        assert_eq!(config.key("default"), "backend:queue:default");
        assert!(Priority::High.queue_priority() > Priority::Normal.queue_priority());
    }
    #[test]
    fn custom_dispatch_requires_a_module_declaration() {
        let mut config = QueueConfig::default();
        config.dispatch.channel = "captions".into();
        assert!(Config::from_config(config.clone()).is_err());
        config.channels.push("captions".into());
        assert!(Config::from_config(config).is_ok());
    }
}
