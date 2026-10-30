use super::PortFuture;
use crate::application::models::GoogleIdentity;

pub trait GoogleIdentityProvider: Send + Sync {
    fn authenticate<'a>(
        &'a self,
        code: &'a str,
        verifier: &'a str,
        nonce: &'a str,
    ) -> PortFuture<'a, GoogleIdentity>;
}
