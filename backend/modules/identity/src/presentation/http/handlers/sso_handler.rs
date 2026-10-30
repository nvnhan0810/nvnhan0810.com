use super::authentication::cookie;
use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        handlers::sso_handler as sso,
        models::AuthorizeRequest,
    },
};
use axum::{
    Json,
    extract::{Query, State},
    http::{HeaderMap, HeaderValue, header},
    response::{IntoResponse, Redirect, Response},
};
use serde::Deserialize;

fn redirect(location: &str, cookies: &[String]) -> Result<Response> {
    let mut response = Redirect::to(location).into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response.headers_mut().insert(
        header::REFERRER_POLICY,
        HeaderValue::from_static("no-referrer"),
    );
    for value in cookies {
        response.headers_mut().append(
            header::SET_COOKIE,
            HeaderValue::from_str(value).map_err(|_| IdentityError::internal())?,
        );
    }
    Ok(response)
}
pub async fn authorize(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Query(intent): Query<AuthorizeRequest>,
) -> Result<Response> {
    let login = cookie(&headers, state.config.session_cookie_name());
    let (location, attempt) = sso::authorize(&state, intent, login.as_deref()).await?;
    redirect(&location, &attempt.into_iter().collect::<Vec<_>>())
}
#[derive(Deserialize)]
pub struct Callback {
    pub state: String,
    pub code: Option<String>,
    pub error: Option<String>,
}
pub async fn callback(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Query(query): Query<Callback>,
) -> Result<Response> {
    if query.state.len() != 43
        || !query
            .state
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-_".contains(&b))
    {
        return Err(IdentityError::bad("invalid_request"));
    }
    let name = state.config.attempt_cookie_name(&query.state);
    let browser = cookie(&headers, &name).ok_or_else(|| IdentityError::bad("invalid_grant"))?;
    if query.error.is_some() {
        return Err(IdentityError::forbidden());
    }
    let code = query
        .code
        .ok_or_else(|| IdentityError::bad("invalid_request"))?;
    let (location, login) = sso::google_callback(&state, &code, &query.state, &browser).await?;
    // Rotate old login on successful authentication.
    if let Some(old) = cookie(&headers, state.config.session_cookie_name()) {
        sso::end_login(&state, &old).await?;
    }
    redirect(
        &location,
        &[login, state.config.cookie_header(&name, "", 0)],
    )
}
pub async fn logout(State(state): State<IdentityState>, headers: HeaderMap) -> Result<Response> {
    // Browser cookie-authenticated mutation: require exact Origin. No GET logout.
    if headers.get(header::ORIGIN).and_then(|v| v.to_str().ok()) != Some(&state.config.app_url) {
        return Err(IdentityError::forbidden());
    }
    if let Some(token) = cookie(&headers, state.config.session_cookie_name()) {
        sso::end_login(&state, &token).await?;
    }
    Ok((
        [(
            header::SET_COOKIE,
            state
                .config
                .cookie_header(state.config.session_cookie_name(), "", 0),
        )],
        Json(serde_json::json!({"success":true})),
    )
        .into_response())
}
