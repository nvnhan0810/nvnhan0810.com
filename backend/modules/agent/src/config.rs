use std::path::{Path, PathBuf};

#[derive(Debug, Clone)]
pub struct AgentConfig {
    pub distribution_dir: PathBuf,
}

impl AgentConfig {
    pub fn new(public_storage_dir: &Path) -> Self {
        Self {
            distribution_dir: public_storage_dir.join("agent").join("distributions"),
        }
    }
}
