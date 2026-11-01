pub mod application;
pub mod domain;
pub mod infrastructure;
pub mod master_data;
pub mod presentation;

use application::{
    config::IdentityConfig,
    error::{IdentityError, Result},
    ports::{GoogleIdentityProvider, IdentityRepository, TokenCrypto},
};
use infrastructure::{google::GoogleProvider, postgres::IdentityStore};
use std::sync::Arc;

pub use presentation::http::routes::identity_routes;

#[derive(Clone)]
pub struct IdentityState {
    pub config: Arc<IdentityConfig>,
    pub store: Arc<dyn IdentityRepository>,
    pub google: Arc<dyn GoogleIdentityProvider>,
    pub crypto: Arc<dyn TokenCrypto>,
}

impl IdentityState {
    pub async fn new(
        config: IdentityConfig,
        pool: sqlx::PgPool,
        private_key_path: &std::path::Path,
        public_key_path: &std::path::Path,
    ) -> Result<Self> {
        let private = std::fs::read(private_key_path).map_err(|_| IdentityError::internal())?;
        let public = std::fs::read(public_key_path).map_err(|_| IdentityError::internal())?;
        let crypto = Arc::new(infrastructure::crypto::JwtCrypto::new(
            &config, &private, &public,
        )?);
        let google = Arc::new(GoogleProvider::new(config.clone())?);
        Ok(Self {
            config: Arc::new(config),
            store: Arc::new(IdentityStore(pool)),
            google,
            crypto,
        })
    }
}

pub use infrastructure::migrations::{MigrationAction, migrate};
