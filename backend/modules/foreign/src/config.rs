//! Foreign job settings. All consumers run in the shared backend queue command.
#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Priority {
    High = 0,
    Normal = 5,
    Low = 9,
}
#[derive(Clone)]
pub struct DispatchConfig {
    pub channel: String,
    pub automatic: Priority,
    pub manual: Priority,
}
impl Default for DispatchConfig {
    fn default() -> Self {
        Self {
            channel: app_resources::queue::DEFAULT_CHANNEL.into(),
            automatic: Priority::Normal,
            manual: Priority::High,
        }
    }
}
/// Shared by API producers and the backend queue command.
#[derive(Clone)]
pub struct QueueConfig {
    /// Extra channels; the shared default channel is always present.
    pub channels: Vec<String>,
    pub dispatch: DispatchConfig,
}
impl Default for QueueConfig {
    fn default() -> Self {
        let dispatch = DispatchConfig::default();
        Self {
            channels: vec![],
            dispatch,
        }
    }
}
