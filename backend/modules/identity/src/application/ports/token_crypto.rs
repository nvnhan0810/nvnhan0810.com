use crate::application::{error::Result, models::AccessClaims};

pub trait TokenCrypto: Send + Sync {
    fn random_token(&self) -> String;
    fn hash(&self, value: &str) -> String;
    fn equal(&self, left: &str, right: &str) -> bool;
    fn valid_verifier(&self, value: &str) -> bool;
    fn sign(&self, claims: &AccessClaims) -> Result<String>;
    fn verify(&self, token: &str) -> Result<AccessClaims>;
}
