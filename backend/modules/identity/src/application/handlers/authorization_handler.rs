use super::token_handler::verify_access;
use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::AccessClaims,
    },
};

pub async fn require_admin(state: &IdentityState, token: &str) -> Result<AccessClaims> {
    let claims = verify_access(state, token)?;
    let user = state.store.profile(claims.sub).await?;
    let root = crate::domain::policies::system_root::is_system_root(&user.email);
    if !root {
        return Err(IdentityError::forbidden());
    }
    if !state
        .store
        .active_grant(claims.grant_id, claims.sub, &claims.client_id)
        .await?
    {
        return Err(IdentityError::forbidden());
    }
    Ok(claims)
}

pub async fn require_root(state: &IdentityState, token: &str) -> Result<AccessClaims> {
    require_admin(state, token).await
}
