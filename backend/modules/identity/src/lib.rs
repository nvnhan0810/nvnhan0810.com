pub mod application;
pub mod domain;
pub mod infrastructure;
pub mod presentation;
pub mod seeder;

use application::{
    config::IdentityConfig,
    error::{IdentityError, Result},
    ports::{GoogleIdentityProvider, IdentityRepository, TokenCrypto},
};
use infrastructure::{google::GoogleProvider, postgres::IdentityStore};
use sqlx::postgres::PgPoolOptions;
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
        database: sqlx::postgres::PgConnectOptions,
        private_key_path: &std::path::Path,
        public_key_path: &std::path::Path,
    ) -> Result<Self> {
        let private = std::fs::read(private_key_path).map_err(|_| IdentityError::internal())?;
        let public = std::fs::read(public_key_path).map_err(|_| IdentityError::internal())?;
        let crypto = Arc::new(infrastructure::crypto::JwtCrypto::new(
            &config, &private, &public,
        )?);
        let pool = PgPoolOptions::new()
            .max_connections(10)
            .acquire_timeout(std::time::Duration::from_secs(10))
            .connect_with(database)
            .await
            .map_err(|_| IdentityError::internal())?;
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
