use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::UserAccount,
    },
};
use uuid::Uuid;

pub async fn list(
    state: &IdentityState,
    token: &str,
    after: Option<Uuid>,
    limit: i64,
) -> Result<Vec<UserAccount>> {
    super::authorization_handler::require_root(state, token).await?;
    state.store.users(after, limit.clamp(1, 100)).await
}
pub async fn get(state: &IdentityState, token: &str, id: Uuid) -> Result<UserAccount> {
    super::authorization_handler::require_root(state, token).await?;
    state.store.user(id).await
}
pub async fn set_enabled(
    state: &IdentityState,
    token: &str,
    id: Uuid,
    enabled: bool,
) -> Result<UserAccount> {
    super::authorization_handler::require_root(state, token).await?;
    let user = state.store.user(id).await?;
    if user.is_system_root {
        return Err(IdentityError::bad("system_root_protected"));
    }
    let user = state.store.set_user_enabled(id, enabled).await?;
    if !enabled {
        state.store.revoke_user(id).await?;
    }
    Ok(user)
}
pub async fn revoke(state: &IdentityState, token: &str, id: Uuid) -> Result<()> {
    super::authorization_handler::require_root(state, token).await?;
    let user = state.store.user(id).await?;
    if user.is_system_root {
        return Err(IdentityError::bad("system_root_protected"));
    }
    state.store.revoke_user(id).await
}

pub async fn create(
    state: &IdentityState,
    token: &str,
    email: &str,
    apps: &[Uuid],
) -> Result<UserAccount> {
    super::authorization_handler::require_root(state, token).await?;
    let email = email.trim().to_lowercase();
    let parts: Vec<_> = email.split('@').collect();
    if email.len() > 254
        || parts.len() != 2
        || parts[0].is_empty()
        || !parts[1].contains('.')
        || parts[1].starts_with('.')
        || parts[1].ends_with('.')
        || email.chars().any(char::is_whitespace)
    {
        return Err(IdentityError::bad("invalid_email"));
    }
    state.store.create_user(&email, apps).await
}
pub async fn set_apps(
    state: &IdentityState,
    token: &str,
    id: Uuid,
    apps: &[Uuid],
) -> Result<UserAccount> {
    super::authorization_handler::require_root(state, token).await?;
    state.store.set_user_apps(id, apps).await
}
