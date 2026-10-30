use crate::domain::errors::authorization_error::AuthorizationError;

pub struct AuthorizationPolicy<'a> {
    pub enabled: bool,
    pub redirect_uris: &'a [String],
    pub scopes: &'a [String],
}

impl AuthorizationPolicy<'_> {
    pub fn validate(&self, redirect: &str, scopes: &str) -> Result<(), AuthorizationError> {
        if !self.enabled || !self.redirect_uris.iter().any(|u| u == redirect) {
            return Err(AuthorizationError::InvalidRequest);
        }
        let mut requested = scopes.split_whitespace().peekable();
        if requested.peek().is_none() || requested.any(|s| !self.scopes.iter().any(|a| a == s)) {
            return Err(AuthorizationError::InvalidScope);
        }
        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn module_permissions_are_scopes_with_exact_callback_matching() {
        let redirects = vec!["https://app.example/cb".into()];
        let scopes = vec!["wallets:read".into(), "todo:read".into()];
        let policy = AuthorizationPolicy {
            enabled: true,
            redirect_uris: &redirects,
            scopes: &scopes,
        };
        assert!(
            policy
                .validate(&redirects[0], "wallets:read todo:read")
                .is_ok()
        );
        assert_eq!(
            policy.validate(&redirects[0], "wallets:write"),
            Err(AuthorizationError::InvalidScope)
        );
        assert_eq!(
            policy.validate("https://other.example/cb", "todo:read"),
            Err(AuthorizationError::InvalidRequest)
        );
    }
}
