use super::authentication::{client_credentials, cookie};
use crate::{
    IdentityState,
    application::{
        config::IdentityConfig,
        error::{IdentityError, Result},
        handlers::token_handler as tokens,
        models::{Client, TokenRequest, TokenResponse},
    },
};
use axum::{
    Form, Json,
    extract::State,
    http::{HeaderMap, header},
    response::{IntoResponse, Response},
};
use serde::Deserialize;

pub(super) fn allows_origin(client: &Client, origin: &str) -> bool {
    client.client_type == "public"
        && client.redirect_uris.iter().any(|uri| {
            url::Url::parse(uri).is_ok_and(|uri| uri.origin().ascii_serialization() == origin)
        })
}
async fn browser_client(
    state: &IdentityState,
    headers: &HeaderMap,
    id: Option<&str>,
) -> Result<Client> {
    let client = client_credentials(state, headers, id, None).await?;
    let origin = headers
        .get(header::ORIGIN)
        .and_then(|v| v.to_str().ok())
        .ok_or_else(IdentityError::forbidden)?;
    if !allows_origin(&client, origin)
        || (state.config.app_url.starts_with("https://") && !origin.starts_with("https://"))
    {
        return Err(IdentityError::forbidden());
    }
    Ok(client)
}
fn cookie_name(config: &IdentityConfig, client: &Client) -> String {
    let prefix = if config.app_url.starts_with("https://") {
        "__Host-"
    } else {
        ""
    };
    format!("{prefix}identity_refresh_{}", client.id)
}
fn clear_cookie(config: &IdentityConfig, name: &str, mut response: Response) -> Response {
    response.headers_mut().insert(
        header::SET_COOKIE,
        config
            .cookie_header(name, "", 0)
            .parse()
            .expect("valid cookie"),
    );
    response.headers_mut().append(
        header::SET_COOKIE,
        config
            .cookie_header(access_cookie_name(config), "", 0)
            .parse()
            .expect("valid cookie"),
    );
    response
}
pub(super) fn clear_client_cookies(
    config: &IdentityConfig,
    client: &Client,
    response: Response,
) -> Response {
    clear_cookie(config, &cookie_name(config, client), response)
}
pub(super) fn issued(
    config: &IdentityConfig,
    client: &Client,
    tokens: TokenResponse,
) -> Result<Response> {
    let name = cookie_name(config, client);
    let age = u32::try_from(config.refresh_ttl).map_err(|_| IdentityError::internal())?;
    let value = config.cookie_header(&name, &tokens.refresh_token, age);
    let access_age = u32::try_from(tokens.expires_in).map_err(|_| IdentityError::internal())?;
    let access = config.cookie_header(access_cookie_name(config), &tokens.access_token, access_age);
    let mut response = (
        [
            (header::SET_COOKIE, value),
            (header::CACHE_CONTROL, "no-store".into()),
        ],
        Json(tokens),
    )
        .into_response();
    response.headers_mut().append(
        header::SET_COOKIE,
        access.parse().map_err(|_| IdentityError::internal())?,
    );
    Ok(response)
}
pub(super) fn access_cookie_name(config: &IdentityConfig) -> &'static str {
    if config.app_url.starts_with("https://") {
        "__Host-identity_access"
    } else {
        "identity_access"
    }
}
pub async fn token(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Form(mut input): Form<TokenRequest>,
) -> Response {
    let client = match browser_client(&state, &headers, input.client_id.as_deref()).await {
        Ok(client) => client,
        Err(error) => return error.into_response(),
    };
    let name = cookie_name(&state.config, &client);
    let result = match input.grant_type.as_str() {
        "authorization_code" => tokens::exchange(&state, &client, input).await,
        "refresh_token" => {
            input.refresh_token = input.refresh_token.or_else(|| cookie(&headers, &name));
            if input.refresh_token.is_none() {
                return clear_cookie(
                    &state.config,
                    &name,
                    IdentityError {
                        status: 401,
                        code: "invalid_grant",
                    }
                    .into_response(),
                );
            }
            tokens::refresh(&state, &client, input).await
        }
        _ => Err(IdentityError::bad("unsupported_grant_type")),
    };
    match result {
        Ok(tokens) => match issued(&state.config, &client, tokens) {
            Ok(response) => response,
            Err(error) => error.into_response(),
        },
        Err(error) => {
            let invalid = error.code == "invalid_grant";
            let response = error.into_response();
            if invalid {
                clear_cookie(&state.config, &name, response)
            } else {
                response
            }
        }
    }
}
#[derive(Deserialize)]
pub struct LogoutRequest {
    pub client_id: String,
    pub token: Option<String>,
}
pub async fn logout(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Form(input): Form<LogoutRequest>,
) -> Response {
    let client = match browser_client(&state, &headers, Some(&input.client_id)).await {
        Ok(client) => client,
        Err(error) => return error.into_response(),
    };
    let name = cookie_name(&state.config, &client);
    let result = if let Some(refresh) = input.token.or_else(|| cookie(&headers, &name)) {
        tokens::revoke(&state, &client, &refresh).await
    } else {
        Ok(())
    };
    let response = match result {
        Ok(()) => (
            [(header::CACHE_CONTROL, "no-store")],
            Json(serde_json::json!({"success": true})),
        )
            .into_response(),
        Err(error) => error.into_response(),
    };
    clear_cookie(&state.config, &name, response)
}

#[cfg(test)]
mod tests {
    use super::*;
    use uuid::Uuid;

    fn config() -> IdentityConfig {
        IdentityConfig {
            app_url: "https://backend.example".into(),
            google_client_id: String::new(),
            google_client_secret: String::new(),
            key_id: "test".into(),
            access_ttl: 900,
            refresh_ttl: 2592000,
        }
    }
    fn client() -> Client {
        Client {
            id: Uuid::new_v4(),
            client_id: "console".into(),
            name: "Console".into(),
            domain: None,
            client_type: "public".into(),
            secret_hash: None,
            redirect_uris: vec!["https://identity.example/auth/callback".into()],
            scopes: vec!["profile".into()],
            enabled: true,
            version: 1,
        }
    }
    #[test]
    fn browser_origin_must_match_the_registered_callback_origin_exactly() {
        let mut client = client();
        assert!(allows_origin(&client, "https://identity.example"));
        for origin in [
            "null",
            "http://identity.example",
            "https://identity.example/",
            "https://identity.example.attacker.com",
            "https://other.example",
        ] {
            assert!(!allows_origin(&client, origin));
        }
        client.client_type = "confidential".into();
        assert!(!allows_origin(&client, "https://identity.example"));
    }
    #[tokio::test]
    async fn token_response_supports_mobile_json_and_persistent_browser_cookies() {
        let config = config();
        let client = client();
        let response = issued(
            &config,
            &client,
            TokenResponse {
                access_token: "signed-access".into(),
                refresh_token: "opaque-refresh".into(),
                token_type: "Bearer".into(),
                expires_in: 900,
                scope: "profile".into(),
            },
        )
        .unwrap();
        let cookies: Vec<_> = response
            .headers()
            .get_all(header::SET_COOKIE)
            .iter()
            .map(|value| value.to_str().unwrap())
            .collect();
        assert_eq!(cookies.len(), 2);
        for cookie in &cookies {
            for attribute in ["__Host-", "Path=/", "Secure", "HttpOnly", "SameSite=Lax"] {
                assert!(cookie.contains(attribute));
            }
            assert!(!cookie.contains("Domain="));
        }
        assert!(
            cookies
                .iter()
                .any(|cookie| cookie.contains("opaque-refresh")
                    && cookie.contains("Max-Age=2592000"))
        );
        assert!(
            cookies
                .iter()
                .any(|cookie| cookie.contains("signed-access") && cookie.contains("Max-Age=900"))
        );
        assert_eq!(response.headers()[header::CACHE_CONTROL], "no-store");
        let body = axum::body::to_bytes(response.into_body(), 32768)
            .await
            .unwrap();
        let json: serde_json::Value = serde_json::from_slice(&body).unwrap();
        assert_eq!(json["access_token"], "signed-access");
        assert_eq!(json["refresh_token"], "opaque-refresh");
        let cleared = clear_cookie(&config, &cookie_name(&config, &client), ().into_response());
        let cookies: Vec<_> = cleared
            .headers()
            .get_all(header::SET_COOKIE)
            .iter()
            .collect();
        assert_eq!(cookies.len(), 2);
        assert!(
            cookies
                .iter()
                .all(|cookie| cookie.to_str().unwrap().contains("Max-Age=0"))
        );
        assert_ne!(
            cookie_name(&config, &client),
            cookie_name(&config, &self::client())
        );
    }
}
