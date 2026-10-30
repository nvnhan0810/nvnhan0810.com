use crate::application::{
    error::Result,
    models::{AuthorizeRequest, Client, ClientInput, GoogleIdentity, LoginAttempt, UserProfile},
    ports::TokenTransaction,
};
use async_trait::async_trait;
use uuid::Uuid;

#[async_trait]
pub trait IdentityRepository: super::user_repository::UserRepository + Send + Sync {
    async fn client(&self, client_id: &str) -> Result<Client>;
    async fn client_by_id(&self, id: Uuid) -> Result<Client>;
    async fn profile(&self, id: Uuid) -> Result<UserProfile>;
    async fn register_client(
        &self,
        id: Uuid,
        input: &ClientInput,
        secret_hash: Option<&str>,
    ) -> Result<Client>;
    async fn update_client(&self, id: Uuid, version: i64, input: &ClientInput) -> Result<Client>;
    async fn rotate_secret(&self, id: Uuid, version: i64, hash: &str) -> Result<Client>;
    async fn list_clients(&self, after: Option<Uuid>, limit: i64) -> Result<Vec<Client>>;
    async fn browser_user(&self, hash: &str) -> Result<Option<Uuid>>;
    async fn save_attempt(&self, state_hash: &str, attempt: LoginAttempt) -> Result<()>;
    async fn consume_attempt(&self, state_hash: &str, browser_hash: &str) -> Result<LoginAttempt>;
    async fn upsert_google_user(&self, identity: GoogleIdentity) -> Result<Uuid>;
    async fn save_browser_login(&self, hash: &str, user: Uuid) -> Result<()>;
    async fn delete_browser_login(&self, hash: &str) -> Result<()>;
    async fn save_code(
        &self,
        hash: &str,
        client: Uuid,
        user: Uuid,
        intent: &AuthorizeRequest,
    ) -> Result<()>;
    async fn active_grant(&self, grant: Uuid, user: Uuid, client_id: &str) -> Result<bool>;
    async fn revoke(&self, client: Uuid, token_hash: &str) -> Result<()>;
    async fn begin(&self) -> Result<Box<dyn TokenTransaction>>;
}
