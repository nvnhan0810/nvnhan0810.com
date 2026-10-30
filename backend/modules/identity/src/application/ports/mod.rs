use crate::application::error::Result;
use std::{future::Future, pin::Pin};
pub type PortFuture<'a, T> = Pin<Box<dyn Future<Output = Result<T>> + Send + 'a>>;

pub mod google_identity_provider;
pub mod repositories;
pub mod token_crypto;
pub mod token_transaction;

pub use google_identity_provider::GoogleIdentityProvider;
pub use repositories::cors_origin_repository::CorsOriginRepository;
pub use repositories::identity_repository::IdentityRepository;
pub use repositories::master_data_repository::MasterDataRepository;
pub use repositories::user_repository::UserRepository;
pub use token_crypto::TokenCrypto;
pub use token_transaction::TokenTransaction;
