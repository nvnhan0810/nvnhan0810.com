use crate::application::{error::Result, models::UserAccount};
use async_trait::async_trait;
use uuid::Uuid;

#[async_trait]
pub trait UserRepository: Send + Sync {
    async fn users(&self, after: Option<Uuid>, limit: i64) -> Result<Vec<UserAccount>>;
    async fn user(&self, id: Uuid) -> Result<UserAccount>;
    async fn set_user_enabled(&self, id: Uuid, enabled: bool) -> Result<UserAccount>;
    async fn revoke_user(&self, id: Uuid) -> Result<()>;
}
