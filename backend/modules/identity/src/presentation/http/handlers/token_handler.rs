use super::authentication::{bearer, client_credentials};
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
    client_response(issue_token(&state, &headers, input).await)
}

fn client_response(result: Result<Response>) -> Response {
    let mut response = match result {
        Ok(response) => response,
        Err(error) => error.into_response(),
    };
    if response.status() == axum::http::StatusCode::UNAUTHORIZED {
        response.headers_mut().insert(
            header::WWW_AUTHENTICATE,
            axum::http::HeaderValue::from_static("Basic realm=\"identity\""),
        );
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
    let result = match input.grant_type.as_str() {
        "authorization_code" => tokens::exchange(state, &client, input).await?,
        "refresh_token" => tokens::refresh(state, &client, input).await?,
        _ => return Err(IdentityError::bad("unsupported_grant_type")),
    };
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
    let profile =
        crate::application::handlers::profile_handler::get(&state, bearer(&headers)?).await?;
    Ok(([(header::CACHE_CONTROL, "no-store")], Json(profile)).into_response())
}
#[derive(Deserialize)]
pub struct RevokeRequest {
    pub token: String,
    pub token_type_hint: Option<String>,
    pub client_id: Option<String>,
    pub client_secret: Option<String>,
}
pub async fn revoke(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Form(input): Form<RevokeRequest>,
) -> Response {
    client_response(revoke_token(&state, &headers, input).await)
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
    tokens::revoke(state, &client, &input.token).await?;
    Ok(([(header::CACHE_CONTROL, "no-store")], "").into_response())
}
