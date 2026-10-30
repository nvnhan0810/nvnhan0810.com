use crate::domain::errors::grant_error::GrantError;

/// A grant owns the lifetime of its refresh-token family. Token material is
/// generated and persisted through application ports, never by this aggregate.
pub struct TokenGrant {
    expires_at: i64,
    revoked_at: Option<i64>,
}

impl TokenGrant {
    pub fn restore(expires_at: i64, revoked_at: Option<i64>) -> Self {
        Self {
            expires_at,
            revoked_at,
        }
    }
    pub fn ensure_active(&self, now: i64) -> Result<(), GrantError> {
        if self.revoked_at.is_some() {
            return Err(GrantError::Revoked);
        }
        if self.expires_at <= now {
            return Err(GrantError::Expired);
        }
        Ok(())
    }
    pub fn revoke(&mut self, now: i64) {
        self.revoked_at.get_or_insert(now);
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn refresh_grant_expiry_and_revocation_are_absolute() {
        let mut grant = TokenGrant::restore(100, None);
        assert!(grant.ensure_active(99).is_ok());
        assert_eq!(grant.ensure_active(100), Err(GrantError::Expired));
        grant.revoke(50);
        assert_eq!(grant.ensure_active(51), Err(GrantError::Revoked));
    }
}
