//! Rate limiting for server-to-server integration routes: one bucket per bearer token
//! (the Agent shares one IP, so the per-IP limiter would throttle it), per peer IP otherwise.
use axum::extract::ConnectInfo;
use axum::http::Request;
use std::{
    hash::{DefaultHasher, Hash, Hasher},
    net::{IpAddr, SocketAddr},
};
use tower_governor::{GovernorError, key_extractor::KeyExtractor};

#[derive(Clone, Debug, Hash, PartialEq, Eq)]
pub enum IntegrationKey {
    Token(u64),
    Ip(IpAddr),
}
#[derive(Clone, Copy)]
pub struct IntegrationKeyExtractor;
impl KeyExtractor for IntegrationKeyExtractor {
    type Key = IntegrationKey;
    fn extract<T>(&self, req: &Request<T>) -> Result<Self::Key, GovernorError> {
        let bearer = req
            .headers()
            .get("authorization")
            .and_then(|v| v.to_str().ok())
            .and_then(|v| v.strip_prefix("Bearer "))
            // Only well-formed integration tokens get their own bucket; anything else shares the IP's.
            .filter(|token| token.starts_with("tdi_") && token.len() <= 200);
        if let Some(token) = bearer {
            let mut hasher = DefaultHasher::new();
            token.hash(&mut hasher);
            return Ok(IntegrationKey::Token(hasher.finish()));
        }
        req.extensions()
            .get::<ConnectInfo<SocketAddr>>()
            .map(|info| IntegrationKey::Ip(info.ip()))
            .ok_or(GovernorError::UnableToExtractKey)
    }
}
