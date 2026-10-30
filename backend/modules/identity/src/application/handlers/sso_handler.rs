use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::{AuthorizeRequest, Client, LoginAttempt},
    },
};
use url::Url;
use uuid::Uuid;

pub fn validate_intent(client: &Client, intent: &AuthorizeRequest) -> Result<()> {
    use crate::domain::{
        errors::authorization_error::AuthorizationError,
        policies::authorization_policy::AuthorizationPolicy,
    };
    AuthorizationPolicy {
        enabled: client.enabled,
        redirect_uris: &client.redirect_uris,
        scopes: &client.scopes,
    }
    .validate(&intent.redirect_uri, &intent.scope)
    .map_err(|error| {
        let reason = match error {
            AuthorizationError::InvalidRequest if !client.enabled => "client_disabled",
            AuthorizationError::InvalidRequest => "redirect_uri_mismatch",
            AuthorizationError::InvalidScope => "scope_not_allowed",
        };
        tracing::warn!(client_id = %client.client_id, reason, "SSO authorization rejected");
        IdentityError::bad(match error {
            AuthorizationError::InvalidRequest => "invalid_request",
            AuthorizationError::InvalidScope => "invalid_scope",
        })
    })?;
    let reason = if intent.response_type != "code" {
        Some("unsupported_response_type")
    } else if intent.state.is_empty() || intent.state.len() > 1024 {
        Some("invalid_state")
    } else if intent.code_challenge_method != "S256" {
        Some("unsupported_pkce_method")
    } else if intent.code_challenge.len() != 43
        || !intent
            .code_challenge
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-_".contains(&b))
    {
        Some("invalid_pkce_challenge")
    } else {
        None
    };
    if let Some(reason) = reason {
        tracing::warn!(client_id = %client.client_id, reason, "SSO authorization rejected");
        return Err(IdentityError::bad("invalid_request"));
    }

    Ok(())
}

pub async fn authorize(
    state: &IdentityState,
    intent: AuthorizeRequest,
    browser_login: Option<&str>,
) -> Result<(String, Option<String>)> {
    let client = state.store.client(&intent.client_id).await?;
    validate_intent(&client, &intent)?;
    if let Some(token) = browser_login {
        let user = state.store.browser_user(&state.crypto.hash(token)).await?;
        if let Some(user_id) = user {
            return Ok((issue_code(state, &intent, user_id).await?, None));
        }
    }
    let google_state = state.crypto.random_token();
    let browser = state.crypto.random_token();
    let nonce = state.crypto.random_token();
    let verifier = state.crypto.random_token();
    state
        .store
        .save_attempt(
            &state.crypto.hash(&google_state),
            LoginAttempt {
                browser_hash: state.crypto.hash(&browser),
                nonce: nonce.clone(),
                verifier: verifier.clone(),
                intent,
            },
        )
        .await?;
    let mut url = Url::parse("https://accounts.google.com/o/oauth2/v2/auth")
        .map_err(|_| IdentityError::internal())?;
    url.query_pairs_mut().extend_pairs([
        ("response_type", "code"),
        ("client_id", state.config.google_client_id.as_str()),
        ("redirect_uri", state.config.google_redirect_uri().as_str()),
        ("scope", "openid email profile"),
        ("state", google_state.as_str()),
        ("nonce", nonce.as_str()),
        ("code_challenge", state.crypto.hash(&verifier).as_str()),
        ("code_challenge_method", "S256"),
    ]);
    // Cookie names are state-specific, allowing multiple tabs to log in concurrently.
    Ok((
        url.to_string(),
        Some(state.config.cookie_header(
            &state.config.attempt_cookie_name(&google_state),
            &browser,
            600,
        )),
    ))
}

pub async fn google_callback(
    state: &IdentityState,
    code: &str,
    google_state: &str,
    browser: &str,
) -> Result<(String, String)> {
    let attempt = state
        .store
        .consume_attempt(
            &state.crypto.hash(google_state),
            &state.crypto.hash(browser),
        )
        .await?;
    let identity = state
        .google
        .authenticate(code, &attempt.verifier, &attempt.nonce)
        .await?;
    let user_id = state.store.upsert_google_user(identity).await?;
    let location = issue_code(state, &attempt.intent, user_id).await?;
    let login = state.crypto.random_token();
    state
        .store
        .save_browser_login(&state.crypto.hash(&login), user_id)
        .await?;
    Ok((
        location,
        state
            .config
            .cookie_header(state.config.session_cookie_name(), &login, 28800),
    ))
}

async fn issue_code(
    state: &IdentityState,
    intent: &AuthorizeRequest,
    user_id: Uuid,
) -> Result<String> {
    let client = state.store.client(&intent.client_id).await?;
    validate_intent(&client, intent)?;
    let user = state.store.profile(user_id).await?;
    if intent
        .scope
        .split_whitespace()
        .any(|s| s == "identity.clients.manage")
        && !crate::domain::policies::system_root::is_system_root(&user.email)
    {
        return Err(IdentityError::forbidden());
    }
    let code = state.crypto.random_token();
    state
        .store
        .save_code(&state.crypto.hash(&code), client.id, user_id, intent)
        .await?;
    let mut url = Url::parse(&intent.redirect_uri).map_err(|_| IdentityError::internal())?;
    url.query_pairs_mut()
        .append_pair("code", &code)
        .append_pair("state", &intent.state);
    Ok(url.into())
}

pub async fn end_login(state: &IdentityState, token: &str) -> Result<()> {
    state
        .store
        .delete_browser_login(&state.crypto.hash(token))
        .await
}
