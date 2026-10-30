use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        handlers::token_handler,
        models::{AccessClaims, Client},
    },
};
use axum::http::{HeaderMap, header};
use base64::{Engine, engine::general_purpose::STANDARD};

pub fn cookie(headers: &HeaderMap, name: &str) -> Option<String> {
    headers
        .get_all(header::COOKIE)
        .iter()
        .filter_map(|v| v.to_str().ok())
        .flat_map(|s| s.split(';'))
        .filter_map(|s| s.trim().split_once('='))
        .find(|(key, _)| *key == name)
        .map(|(_, value)| value.to_owned())
}

pub fn bearer(headers: &HeaderMap) -> Result<&str> {
    headers
        .get(header::AUTHORIZATION)
        .and_then(|v| v.to_str().ok())
        .and_then(|s| s.strip_prefix("Bearer "))
        .filter(|s| !s.is_empty())
        .ok_or_else(IdentityError::unauthorized)
}

pub async fn client_credentials(
    state: &IdentityState,
    headers: &HeaderMap,
    id: Option<&str>,
    secret: Option<&str>,
) -> Result<Client> {
    if let Some(header) = headers.get(header::AUTHORIZATION) {
        if secret.is_some() {
            return Err(IdentityError::bad("invalid_request"));
        }
        let value = header
            .to_str()
            .ok()
            .and_then(|s| s.strip_prefix("Basic "))
            .ok_or_else(IdentityError::unauthorized)?;
        let decoded = STANDARD
            .decode(value)
            .map_err(|_| IdentityError::unauthorized())?;
        let text = String::from_utf8(decoded).map_err(|_| IdentityError::unauthorized())?;
        let (basic_id, basic_secret) = text
            .split_once(':')
            .ok_or_else(IdentityError::unauthorized)?;
        // OAuth Basic credentials are form-url-encoded before Base64.
        let decode = |s: &str| {
            url::form_urlencoded::parse(format!("v={s}").as_bytes())
                .next()
                .map(|(_, v)| v.into_owned())
                .ok_or_else(IdentityError::unauthorized)
        };
        let basic_id = decode(basic_id)?;
        let basic_secret = decode(basic_secret)?;
        if id.is_some_and(|id| id != basic_id) {
            return Err(IdentityError::bad("invalid_request"));
        }
        return token_handler::authenticate_client(state, &basic_id, Some(&basic_secret)).await;
    }
    token_handler::authenticate_client(state, id.ok_or_else(IdentityError::unauthorized)?, secret)
        .await
}

pub async fn admin(state: &IdentityState, headers: &HeaderMap) -> Result<AccessClaims> {
    crate::application::handlers::authorization_handler::require_admin(state, bearer(headers)?)
        .await
}
