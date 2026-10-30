use crate::application::{
    error::Result,
    models::{Client, CodeRecord, GrantRecord},
};
use async_trait::async_trait;
use uuid::Uuid;

#[async_trait]
pub trait TokenTransaction: Send {
    async fn lock_client(&mut self, id: Uuid) -> Result<Client>;
    async fn consume_code(
        &mut self,
        hash: &str,
        client: Uuid,
        redirect: &str,
        challenge: &str,
    ) -> Result<CodeRecord>;
    async fn user_email(&mut self, id: Uuid) -> Result<String>;
    async fn create_grant(
        &mut self,
        id: Uuid,
        client: Uuid,
        code: &CodeRecord,
        expires: chrono::DateTime<chrono::Utc>,
    ) -> Result<()>;
    async fn insert_refresh(&mut self, hash: &str, grant: Uuid) -> Result<()>;
    async fn lock_grant(&mut self, hash: &str, client: Uuid) -> Result<GrantRecord>;
    async fn refresh_consumed(&mut self, hash: &str) -> Result<bool>;
    async fn revoke_grant(&mut self, id: Uuid) -> Result<()>;
    async fn consume_refresh(&mut self, hash: &str) -> Result<()>;
    async fn commit(self: Box<Self>) -> Result<()>;
}
