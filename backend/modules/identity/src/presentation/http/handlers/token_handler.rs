use super::authentication::{access_token, client_credentials};
use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        handlers::token_handler as tokens,
        models::TokenRequest,
    },
};
use axum::{
    Form, Json,
    extract::State,
    http::{HeaderMap, header},
    response::{IntoResponse, Response},
};
use serde::Deserialize;

pub async fn token(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Form(input): Form<TokenRequest>,
) -> Response {
    client_response(issue_token(&state, &headers, input).await, &headers)
}

fn client_response(result: Result<Response>, request_headers: &HeaderMap) -> Response {
    let mut response = match result {
        Ok(response) => response,
        Err(error) => error.into_response(),
    };
    // A missing browser session also returns 401. Challenging it with Basic
    // opens the browser's credential dialog instead of showing our login page.
    let basic_attempt = !request_headers.contains_key(header::ORIGIN)
        && request_headers
            .get(header::AUTHORIZATION)
            .and_then(|value| value.to_str().ok())
            .is_some_and(|value| value.starts_with("Basic "));
    if basic_attempt && response.status() == axum::http::StatusCode::UNAUTHORIZED {
        response.headers_mut().insert(
            header::WWW_AUTHENTICATE,
            axum::http::HeaderValue::from_static("Basic realm=\"identity\""),
        );
    } else {
        // Resource API errors use a Bearer challenge globally; token endpoint
        // failures without HTTP client authentication must not issue a challenge.
        response.headers_mut().remove(header::WWW_AUTHENTICATE);
    }
    response
}

async fn issue_token(
    state: &IdentityState,
    headers: &HeaderMap,
    input: TokenRequest,
) -> Result<Response> {
    let client = client_credentials(
        state,
        headers,
        input.client_id.as_deref(),
        input.client_secret.as_deref(),
    )
    .await?;
    if client.client_type == "public" && headers.contains_key(header::ORIGIN) {
        return Ok(super::browser_session::token(
            State(state.clone()),
            headers.clone(),
            Form(input),
        )
        .await);
    }
    let result = match input.grant_type.as_str() {
        "authorization_code" => tokens::exchange(state, &client, input).await?,
        "refresh_token" => tokens::refresh(state, &client, input).await?,
        _ => return Err(IdentityError::bad("unsupported_grant_type")),
    };
    if client.client_type == "public" {
        return super::browser_session::issued(&state.config, &client, result);
    }
    Ok((
        [
            (header::CACHE_CONTROL, "no-store"),
            (header::PRAGMA, "no-cache"),
        ],
        Json(result),
    )
        .into_response())
}
pub async fn profile(State(state): State<IdentityState>, headers: HeaderMap) -> Result<Response> {
    let profile = crate::application::handlers::profile_handler::get(
        &state,
        &access_token(&state, &headers, false).await?,
    )
    .await?;
    Ok(([(header::CACHE_CONTROL, "no-store")], Json(profile)).into_response())
}
#[derive(Deserialize)]
pub struct RevokeRequest {
    pub token: Option<String>,
    pub token_type_hint: Option<String>,
    pub client_id: Option<String>,
    pub client_secret: Option<String>,
}
pub async fn revoke(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Form(input): Form<RevokeRequest>,
) -> Response {
    client_response(revoke_token(&state, &headers, input).await, &headers)
}

async fn revoke_token(
    state: &IdentityState,
    headers: &HeaderMap,
    input: RevokeRequest,
) -> Result<Response> {
    let client = client_credentials(
        state,
        headers,
        input.client_id.as_deref(),
        input.client_secret.as_deref(),
    )
    .await?;
    if input
        .token_type_hint
        .as_deref()
        .is_some_and(|s| s != "refresh_token")
    {
        return Err(IdentityError::bad("unsupported_token_type"));
    }
    if client.client_type == "public" && headers.contains_key(header::ORIGIN) {
        return Ok(super::browser_session::logout(
            State(state.clone()),
            headers.clone(),
            Form(super::browser_session::LogoutRequest {
                client_id: client.client_id,
                token: input.token,
            }),
        )
        .await);
    }
    tokens::revoke(
        state,
        &client,
        input
            .token
            .as_deref()
            .ok_or_else(|| IdentityError::bad("invalid_request"))?,
    )
    .await?;
    let response = ([(header::CACHE_CONTROL, "no-store")], "").into_response();
    Ok(if client.client_type == "public" {
        super::browser_session::clear_client_cookies(&state.config, &client, response)
    } else {
        response
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::application::error::IdentityError;

    #[test]
    fn browser_session_and_public_client_errors_do_not_challenge_with_basic() {
        for code in ["invalid_grant", "invalid_client"] {
            let response =
                client_response(Err(IdentityError { status: 401, code }), &HeaderMap::new());
            assert_eq!(response.status(), axum::http::StatusCode::UNAUTHORIZED);
            assert!(!response.headers().contains_key(header::WWW_AUTHENTICATE));
        }
    }

    #[test]
    fn explicit_basic_auth_failure_keeps_its_challenge() {
        let mut headers = HeaderMap::new();
        headers.insert(header::AUTHORIZATION, "Basic aW52YWxpZA==".parse().unwrap());
        let response = client_response(Err(IdentityError::unauthorized()), &headers);
        assert_eq!(
            response.headers()[header::WWW_AUTHENTICATE],
            "Basic realm=\"identity\""
        );
        headers.insert(header::ORIGIN, "http://localhost:5174".parse().unwrap());
        let browser_response = client_response(Err(IdentityError::unauthorized()), &headers);
        assert!(
            !browser_response
                .headers()
                .contains_key(header::WWW_AUTHENTICATE)
        );
    }
}
