use crate::application::{error::Result, models::ClientInput};
use async_trait::async_trait;
use uuid::Uuid;

#[async_trait]
pub trait MasterDataRepository: Send + Sync {
    /// Returns the public client ID only when this call created the row.
    async fn insert_client_if_missing(
        &self,
        id: Uuid,
        input: &ClientInput,
    ) -> Result<Option<String>>;
}
