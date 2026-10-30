use serde::{Deserialize, Serialize};
use uuid::Uuid;

#[derive(Clone, Serialize, Deserialize)]
pub struct Client {
    pub id: Uuid,
    pub client_id: String,
    pub name: String,
    pub domain: Option<String>,
    pub client_type: String,
    #[serde(skip_serializing)]
    pub secret_hash: Option<String>,
    pub redirect_uris: Vec<String>,
    pub scopes: Vec<String>,
    pub enabled: bool,
    pub version: i64,
}

#[derive(Clone, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct ClientInput {
    pub client_id: String,
    pub name: String,
    pub domain: Option<String>,
    pub client_type: String,
    pub redirect_uris: Vec<String>,
    pub scopes: Vec<String>,
    pub enabled: bool,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct ClientUpdate {
    pub version: i64,
    pub name: String,
    pub domain: Option<String>,
    pub redirect_uris: Vec<String>,
    pub scopes: Vec<String>,
    pub enabled: bool,
}

#[derive(Clone, Serialize, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct AuthorizeRequest {
    pub response_type: String,
    pub client_id: String,
    pub redirect_uri: String,
    pub scope: String,
    pub state: String,
    pub code_challenge: String,
    pub code_challenge_method: String,
}

#[derive(Deserialize)]
pub struct TokenRequest {
    pub grant_type: String,
    pub client_id: Option<String>,
    pub client_secret: Option<String>,
    pub code: Option<String>,
    pub redirect_uri: Option<String>,
    pub code_verifier: Option<String>,
    pub refresh_token: Option<String>,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct TokenResponse {
    pub access_token: String,
    pub token_type: String,
    pub expires_in: i64,
    pub refresh_token: String,
    pub scope: String,
}

#[derive(Clone, Serialize, Deserialize)]
pub struct AccessClaims {
    pub iss: String,
    pub sub: Uuid,
    pub aud: String,
    pub client_id: String,
    pub scope: String,
    pub iat: i64,
    pub exp: i64,
    pub jti: Uuid,
    pub grant_id: Uuid,
    #[serde(default)]
    pub is_system_root: bool,
}

#[derive(Serialize)]
pub struct UserProfile {
    pub id: Uuid,
    pub email: String,
    pub name: String,
    pub avatar: Option<String>,
}

pub struct GoogleIdentity {
    pub subject: String,
    pub email: String,
    pub name: String,
    pub avatar: Option<String>,
}

pub struct LoginAttempt {
    pub browser_hash: String,
    pub nonce: String,
    pub verifier: String,
    pub intent: AuthorizeRequest,
}

pub struct CodeRecord {
    pub user_id: Uuid,
    pub scopes: Vec<String>,
}
pub struct GrantRecord {
    pub id: Uuid,
    pub user_id: Uuid,
    pub scopes: Vec<String>,
    pub revoked_at: Option<chrono::DateTime<chrono::Utc>>,
    pub expires_at: chrono::DateTime<chrono::Utc>,
}

#[derive(Serialize)]
pub struct UserAccount {
    pub id: Uuid,
    pub email: String,
    pub name: String,
    pub avatar: Option<String>,
    pub enabled: bool,
    pub is_system_root: bool,
}
