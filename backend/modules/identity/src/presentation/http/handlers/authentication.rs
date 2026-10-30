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

pub async fn access_token(
    state: &IdentityState,
    headers: &HeaderMap,
    mutation: bool,
) -> Result<String> {
    // Explicit Authorization always takes precedence, including malformed headers.
    if headers.contains_key(header::AUTHORIZATION) {
        return bearer(headers).map(str::to_owned);
    }
    let token = cookie(
        headers,
        super::browser_session::access_cookie_name(&state.config),
    )
    .ok_or_else(IdentityError::unauthorized)?;
    let claims = token_handler::verify_access(state, &token)?;
    // Check cross-origin reads too: another registered app must not borrow the
    // console's cookie just because its own origin is allowed by global CORS.
    if mutation || headers.contains_key(header::ORIGIN) {
        let origin = headers
            .get(header::ORIGIN)
            .and_then(|value| value.to_str().ok())
            .ok_or_else(IdentityError::forbidden)?;
        let client = token_handler::authenticate_client(state, &claims.client_id, None).await?;
        if !super::browser_session::allows_origin(&client, origin) {
            return Err(IdentityError::forbidden());
        }
    }
    Ok(token)
}
pub async fn admin(
    state: &IdentityState,
    headers: &HeaderMap,
    mutation: bool,
) -> Result<AccessClaims> {
    crate::application::handlers::authorization_handler::require_admin(
        state,
        &access_token(state, headers, mutation).await?,
    )
    .await
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::{
        application::config::IdentityConfig,
        infrastructure::{crypto::JwtCrypto, google::GoogleProvider, postgres::IdentityStore},
    };
    use chrono::Utc;
    use std::sync::Arc;
    use uuid::Uuid;

    #[tokio::test]
    async fn cookie_auth_checks_signature_expiry_csrf_and_bearer_precedence() {
        let config = IdentityConfig {
            app_url: "https://backend.example".into(),
            google_client_id: "fixture".into(),
            google_client_secret: "fixture".into(),
            key_id: "fixture".into(),
            access_ttl: 900,
            refresh_ttl: 2592000,
        };
        let crypto = JwtCrypto::new(
            &config,
            include_bytes!("../../../../tests/fixtures/private.pem"),
            include_bytes!("../../../../tests/fixtures/public.pem"),
        )
        .unwrap();
        // No database connection is made: these paths must reject invalid
        // cookies/CSRF before looking up a client or grant.
        let pool = sqlx::postgres::PgPoolOptions::new()
            .connect_lazy("postgres://unused:unused@localhost/unused")
            .unwrap();
        let state = IdentityState {
            google: Arc::new(GoogleProvider::new(config.clone()).unwrap()),
            config: Arc::new(config),
            store: Arc::new(IdentityStore(pool)),
            crypto: Arc::new(crypto),
        };
        let mut claims = AccessClaims {
            iss: state.config.app_url.clone(),
            aud: state.config.app_url.clone(),
            sub: Uuid::new_v4(),
            client_id: "console".into(),
            scope: "profile".into(),
            iat: Utc::now().timestamp(),
            exp: Utc::now().timestamp() + 900,
            jti: Uuid::new_v4(),
            grant_id: Uuid::new_v4(),
            is_system_root: false,
        };
        let token = state.crypto.sign(&claims).unwrap();
        let mut headers = HeaderMap::new();
        headers.insert(
            header::COOKIE,
            format!("__Host-identity_access={token}").parse().unwrap(),
        );
        assert_eq!(access_token(&state, &headers, false).await.unwrap(), token);
        assert_eq!(
            access_token(&state, &headers, true)
                .await
                .unwrap_err()
                .status,
            403
        );
        headers.insert(header::AUTHORIZATION, "Bearer explicit".parse().unwrap());
        assert_eq!(
            access_token(&state, &headers, true).await.unwrap(),
            "explicit"
        );
        headers.insert(header::AUTHORIZATION, "Basic malformed".parse().unwrap());
        assert!(access_token(&state, &headers, false).await.is_err());
        headers.remove(header::AUTHORIZATION);
        headers.insert(
            header::COOKIE,
            format!("__Host-identity_access={token}tampered")
                .parse()
                .unwrap(),
        );
        assert!(access_token(&state, &headers, false).await.is_err());
        claims.exp = Utc::now().timestamp() - 3600;
        let expired = state.crypto.sign(&claims).unwrap();
        headers.insert(
            header::COOKIE,
            format!("__Host-identity_access={expired}").parse().unwrap(),
        );
        assert!(access_token(&state, &headers, false).await.is_err());
    }
}
