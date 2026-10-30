use chrono::{Duration, Utc};
use uuid::Uuid;

use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::{AccessClaims, Client, TokenRequest, TokenResponse},
        ports::TokenTransaction,
    },
};

pub async fn authenticate_client(
    state: &IdentityState,
    id: &str,
    secret: Option<&str>,
) -> Result<Client> {
    let client = state.store.client(id).await?;
    if !client.enabled {
        return Err(IdentityError::unauthorized());
    }
    match client.client_type.as_str() {
        "confidential"
            if client
                .secret_hash
                .as_ref()
                .zip(secret)
                .is_some_and(|(expected, actual)| {
                    state.crypto.equal(expected, &state.crypto.hash(actual))
                }) => {}
        "public" if secret.is_none() => {}
        _ => return Err(IdentityError::unauthorized()),
    }
    Ok(client)
}

pub fn verify_access(state: &IdentityState, token: &str) -> Result<AccessClaims> {
    state.crypto.verify(token)
}

fn token_response(
    state: &IdentityState,
    client: &Client,
    user: (Uuid, bool),
    grant_id: Uuid,
    scopes: &[String],
    refresh: String,
) -> Result<TokenResponse> {
    let now = Utc::now().timestamp();
    let claims = AccessClaims {
        iss: state.config.app_url.clone(),
        sub: user.0,
        aud: state.config.app_url.clone(),
        client_id: client.client_id.clone(),
        scope: scopes.join(" "),
        iat: now,
        exp: now + state.config.access_ttl,
        jti: Uuid::new_v4(),
        grant_id,
        is_system_root: user.1,
    };
    let access_token = state.crypto.sign(&claims)?;
    Ok(TokenResponse {
        access_token,
        token_type: "Bearer".into(),
        expires_in: state.config.access_ttl,
        refresh_token: refresh,
        scope: scopes.join(" "),
    })
}

async fn validate_user_policy(
    tx: &mut dyn TokenTransaction,
    user_id: Uuid,
    scopes: &[String],
) -> Result<bool> {
    let email = tx.user_email(user_id).await?.to_lowercase();
    if scopes.iter().any(|s| s == "identity.clients.manage")
        && !crate::domain::policies::system_root::is_system_root(&email)
    {
        return Err(IdentityError::bad("invalid_grant"));
    }
    Ok(crate::domain::policies::system_root::is_system_root(&email))
}

pub async fn exchange(
    state: &IdentityState,
    client: &Client,
    request: TokenRequest,
) -> Result<TokenResponse> {
    let code = request
        .code
        .ok_or_else(|| IdentityError::bad("invalid_request"))?;
    let redirect = request
        .redirect_uri
        .ok_or_else(|| IdentityError::bad("invalid_request"))?;
    let verifier = request
        .code_verifier
        .filter(|s| state.crypto.valid_verifier(s))
        .ok_or_else(|| IdentityError::bad("invalid_grant"))?;
    let mut tx = state.store.begin().await?;
    // A concurrent client disable/secret rotation must finish before issuance or
    // wait until this transaction completes.
    let locked = tx.lock_client(client.id).await?;
    if !locked.enabled || locked.secret_hash != client.secret_hash {
        return Err(IdentityError::unauthorized());
    }
    let record = tx
        .consume_code(
            &state.crypto.hash(&code),
            client.id,
            &redirect,
            &state.crypto.hash(&verifier),
        )
        .await?;
    let user_id = record.user_id;
    let scopes = &record.scopes;
    if !locked.redirect_uris.contains(&redirect)
        || scopes.iter().any(|s| !locked.scopes.contains(s))
    {
        return Err(IdentityError::bad("invalid_grant"));
    }
    let is_system_root = validate_user_policy(tx.as_mut(), user_id, scopes).await?;
    let grant_id = Uuid::new_v4();
    let refresh = state.crypto.random_token();
    let response = token_response(
        state,
        client,
        (user_id, is_system_root),
        grant_id,
        scopes,
        refresh.clone(),
    )?;
    tx.create_grant(
        grant_id,
        client.id,
        &record,
        Utc::now() + Duration::seconds(state.config.refresh_ttl),
    )
    .await?;
    tx.insert_refresh(&state.crypto.hash(&refresh), grant_id)
        .await?;
    tx.commit().await?;
    Ok(response)
}

pub async fn refresh(
    state: &IdentityState,
    client: &Client,
    request: TokenRequest,
) -> Result<TokenResponse> {
    let raw = request
        .refresh_token
        .ok_or_else(|| IdentityError::bad("invalid_request"))?;
    let hash = state.crypto.hash(&raw);
    let mut tx = state.store.begin().await?;
    let locked = tx.lock_client(client.id).await?;
    if !locked.enabled || locked.secret_hash != client.secret_hash {
        return Err(IdentityError::unauthorized());
    }
    // Lock the grant before touching a refresh token, serializing the whole family.
    let grant = tx.lock_grant(&hash, client.id).await?;
    let grant_id = grant.id;
    // Read token state after acquiring the family lock.
    let consumed = tx.refresh_consumed(&hash).await?;
    if consumed {
        tx.revoke_grant(grant_id).await?;
        tx.commit().await?;
        return Err(IdentityError::bad("invalid_grant"));
    }
    crate::domain::entities::token_grant::TokenGrant::restore(
        grant.expires_at.timestamp(),
        grant.revoked_at.map(|t| t.timestamp()),
    )
    .ensure_active(Utc::now().timestamp())
    .map_err(|_| IdentityError::bad("invalid_grant"))?;
    let user_id = grant.user_id;
    let scopes = &grant.scopes;
    if scopes.iter().any(|s| !locked.scopes.contains(s)) {
        return Err(IdentityError::bad("invalid_grant"));
    }
    let is_system_root = validate_user_policy(tx.as_mut(), user_id, scopes).await?;
    let successor = state.crypto.random_token();
    let response = token_response(
        state,
        client,
        (user_id, is_system_root),
        grant_id,
        scopes,
        successor.clone(),
    )?;
    tx.consume_refresh(&hash).await?;
    tx.insert_refresh(&state.crypto.hash(&successor), grant_id)
        .await?;
    tx.commit().await?;
    Ok(response)
}

pub async fn revoke(state: &IdentityState, client: &Client, token: &str) -> Result<()> {
    state
        .store
        .revoke(client.id, &state.crypto.hash(token))
        .await
}
