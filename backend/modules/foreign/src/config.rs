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
            channel: "youtube-transcript".into(),
            automatic: Priority::Normal,
            manual: Priority::High,
        }
    }
}
/// Shared by the API and the dedicated transcript worker.
#[derive(Clone)]
pub struct QueueConfig {
    pub prefix: String,
    pub channels: Vec<String>,
    pub dispatch: DispatchConfig,
    /// Reserved worker counts in high, normal, low order.
    pub workers: [usize; 3],
}
impl Default for QueueConfig {
    fn default() -> Self {
        let dispatch = DispatchConfig::default();
        Self {
            prefix: "foreign:queue".into(),
            channels: vec![dispatch.channel.clone()],
            dispatch,
            workers: [2, 1, 1],
        }
    }
}
