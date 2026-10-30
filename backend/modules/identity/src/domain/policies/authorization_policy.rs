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

#[cfg(test)]
mod local_callback_tests {
    use super::*;
    #[test]
    fn forwarded_localhost_requires_its_exact_registered_callback() {
        let redirects = vec!["http://localhost:5174/auth/callback".into()];
        let scopes = vec!["profile".into()];
        let policy = AuthorizationPolicy {
            enabled: true,
            redirect_uris: &redirects,
            scopes: &scopes,
        };
        assert!(policy.validate(&redirects[0], "profile").is_ok());
        for callback in [
            "http://127.0.0.1:5174/auth/callback",
            "http://localhost:5173/auth/callback",
            "https://identity.nvnhan0810.com/auth/callback",
        ] {
            assert_eq!(
                policy.validate(callback, "profile"),
                Err(AuthorizationError::InvalidRequest)
            );
        }
    }
}
