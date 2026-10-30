use super::token_handler::verify_access;
use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::UserProfile,
    },
};

pub async fn get(state: &IdentityState, token: &str) -> Result<UserProfile> {
    let claims = verify_access(state, token)?;
    if !claims.scope.split_whitespace().any(|s| s == "profile")
        || !state
            .store
            .active_grant(claims.grant_id, claims.sub, &claims.client_id)
            .await?
    {
        return Err(IdentityError::forbidden());
    }
    state.store.profile(claims.sub).await
}
