//! Todo <-> Agent account link (Phase 2).
//!
//! Two opaque 32-byte tokens per link: `tdi_` (Agent -> Todo, only its SHA-256 is stored here)
//! and `agi_` (Todo -> Agent, stored sealed with AES-256-GCM). Linking requires the *same*
//! Identity UUID and e-mail on both sides, and the user must still be enabled.
use aes_gcm::{
    Aes256Gcm, KeyInit, Nonce,
    aead::{Aead, Payload},
};
use base64::{
    Engine,
    engine::general_purpose::{STANDARD, URL_SAFE_NO_PAD},
};
use rand::{RngCore, rngs::OsRng};
use serde_json::{Value, json};
use sha2::{Digest, Sha256};
use sqlx::PgPool;
use uuid::Uuid;

pub const INBOUND_PREFIX: &str = "tdi_";
pub const OUTBOUND_PREFIX: &str = "agi_";
const REQUEST_TTL_MINUTES: i64 = 10;
const MAX_OPEN_REQUESTS: i64 = 5;

#[derive(Clone, Default)]
pub struct AgentLinkConfig {
    pub enabled: bool,
    /// Agent API origin (`https://agent.nvnhan0810.com`), used for server-to-server calls.
    pub agent_base_url: String,
    /// Agent web origin, used to build the consent redirect.
    pub agent_web_url: String,
    /// Todo web origin (`TODO_WEB_ORIGIN`), used to build `todo_url` in claim payloads. Empty = unset.
    pub todo_web_url: String,
    key: Option<[u8; 32]>,
}

impl AgentLinkConfig {
    /// `TODO_AGENT_INTEGRATION_ENABLED`, `AGENT_BASE_URL`, `AGENT_WEB_URL`, `TODO_INTEGRATION_KEY`.
    pub fn from_source(source: &app_config::ConfigSource) -> Result<Self, String> {
        let enabled = matches!(
            source.optional("TODO_AGENT_INTEGRATION_ENABLED"),
            Some("1" | "true" | "TRUE" | "yes")
        );
        if !enabled {
            return Ok(Self::default());
        }
        let origin = |name: &str, value: &str| -> Result<String, String> {
            let url = url::Url::parse(value).map_err(|_| format!("{name} must be a URL"))?;
            if !matches!(url.scheme(), "http" | "https")
                || url.host_str().is_none()
                || url.path() != "/"
                || url.query().is_some()
                || url.fragment().is_some()
                || !url.username().is_empty()
                || url.password().is_some()
            {
                return Err(format!("{name} must be an HTTP(S) origin"));
            }
            Ok(value.trim_end_matches('/').to_owned())
        };
        let base = origin(
            "AGENT_BASE_URL",
            source
                .optional("AGENT_BASE_URL")
                .ok_or("AGENT_BASE_URL is required when the Agent integration is enabled")?,
        )?;
        let web = match source.optional("AGENT_WEB_URL") {
            Some(value) => origin("AGENT_WEB_URL", value)?,
            None => base.clone(),
        };
        let raw = source
            .optional("TODO_INTEGRATION_KEY")
            .ok_or("TODO_INTEGRATION_KEY is required when the Agent integration is enabled")?;
        let key: [u8; 32] = STANDARD
            .decode(raw.trim())
            .ok()
            .and_then(|bytes| bytes.try_into().ok())
            .ok_or("TODO_INTEGRATION_KEY must be base64 of exactly 32 bytes")?;
        let todo_web_url = source
            .optional("TODO_WEB_ORIGIN")
            .map(|v| v.trim().trim_end_matches('/').to_owned())
            .unwrap_or_default();
        Ok(Self {
            enabled,
            agent_base_url: base,
            agent_web_url: web,
            todo_web_url,
            key: Some(key),
        })
    }
    /// For tests and tools: build a config without going through the environment.
    pub fn new(agent_base_url: &str, agent_web_url: &str, key: [u8; 32]) -> Self {
        Self {
            enabled: true,
            agent_base_url: agent_base_url.trim_end_matches('/').into(),
            agent_web_url: agent_web_url.trim_end_matches('/').into(),
            todo_web_url: String::new(),
            key: Some(key),
        }
    }
    fn key(&self) -> Result<&[u8; 32], LinkError> {
        if !self.enabled {
            return Err(LinkError::new(503, "integration_disabled"));
        }
        self.key
            .as_ref()
            .ok_or(LinkError::new(503, "integration_disabled"))
    }
}

#[derive(Debug)]
pub struct LinkError {
    pub status: u16,
    pub code: &'static str,
}
impl LinkError {
    pub fn new(status: u16, code: &'static str) -> Self {
        Self { status, code }
    }
}
impl From<sqlx::Error> for LinkError {
    fn from(error: sqlx::Error) -> Self {
        tracing::error!(%error, "Agent link database operation failed");
        Self::new(500, "server_error")
    }
}
pub type LinkResult<T> = Result<T, LinkError>;

fn random_token(prefix: &str) -> String {
    let mut bytes = [0u8; 32];
    OsRng.fill_bytes(&mut bytes);
    format!("{prefix}{}", URL_SAFE_NO_PAD.encode(bytes))
}
pub fn hash(value: &str) -> String {
    format!("{:x}", Sha256::digest(value.as_bytes()))
}
fn aad(user: Uuid) -> String {
    format!("todo-agent-link:v1:{user}")
}
#[allow(deprecated)] // aes-gcm 0.10 `Nonce::from_slice`; same API the Agent backend uses
pub fn seal(config: &AgentLinkConfig, user: Uuid, clear: &str) -> LinkResult<String> {
    let cipher = Aes256Gcm::new_from_slice(config.key()?)
        .map_err(|_| LinkError::new(500, "server_error"))?;
    let mut nonce = [0u8; 12];
    OsRng.fill_bytes(&mut nonce);
    let encrypted = cipher
        .encrypt(
            Nonce::from_slice(&nonce),
            Payload {
                msg: clear.as_bytes(),
                aad: aad(user).as_bytes(),
            },
        )
        .map_err(|_| LinkError::new(500, "server_error"))?;
    Ok(format!(
        "v1.{}",
        STANDARD.encode([nonce.as_slice(), encrypted.as_slice()].concat())
    ))
}
#[allow(deprecated)]
pub fn unseal(config: &AgentLinkConfig, user: Uuid, value: &str) -> LinkResult<String> {
    let fail = || LinkError::new(500, "server_error");
    let bytes = STANDARD
        .decode(value.strip_prefix("v1.").ok_or_else(fail)?)
        .map_err(|_| fail())?;
    if bytes.len() < 28 {
        return Err(fail());
    }
    let cipher = Aes256Gcm::new_from_slice(config.key()?).map_err(|_| fail())?;
    let clear = cipher
        .decrypt(
            Nonce::from_slice(&bytes[..12]),
            Payload {
                msg: &bytes[12..],
                aad: aad(user).as_bytes(),
            },
        )
        .map_err(|_| fail())?;
    String::from_utf8(clear).map_err(|_| fail())
}

/// Step 1: the Todo UI asks for a consent redirect. Returns the URL for the browser.
pub async fn start(pool: &PgPool, config: &AgentLinkConfig, user: Uuid) -> LinkResult<Value> {
    config.key()?;
    let id = Uuid::new_v4();
    let code = random_token("");
    let mut tx = pool.begin().await?;
    // Keep at most a handful of open requests per user; drop expired and used ones.
    sqlx::query("DELETE FROM todo_agent_link_requests WHERE user_id=$1 AND (expires_at<now() OR used_at IS NOT NULL)")
        .bind(user)
        .execute(&mut *tx)
        .await?;
    sqlx::query("DELETE FROM todo_agent_link_requests WHERE id IN (SELECT id FROM todo_agent_link_requests WHERE user_id=$1 ORDER BY created_at DESC OFFSET $2)")
        .bind(user)
        .bind(MAX_OPEN_REQUESTS - 1)
        .execute(&mut *tx)
        .await?;
    sqlx::query("INSERT INTO todo_agent_link_requests(id,user_id,code_hash,expires_at) VALUES($1,$2,$3,now()+make_interval(mins=>$4::int))")
        .bind(id)
        .bind(user)
        .bind(hash(&code))
        .bind(REQUEST_TTL_MINUTES as i32)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    // The code travels in the URL fragment so it never reaches server logs or Referer headers.
    Ok(json!({
        "redirect_url": format!("{}/#/integrations/todo/consent?req={id}&code={code}", config.agent_web_url),
        "expires_in": REQUEST_TTL_MINUTES * 60,
    }))
}

pub struct CompleteInput {
    pub request_id: Uuid,
    pub code: String,
    pub identity_user_id: Uuid,
    pub email: String,
    pub agent_token: String,
}

/// Step 2: the Agent proves the consent. Returns the `tdi_` token for the Agent to keep.
pub async fn complete(
    pool: &PgPool,
    config: &AgentLinkConfig,
    input: CompleteInput,
) -> LinkResult<String> {
    config.key()?;
    if !input.agent_token.starts_with(OUTBOUND_PREFIX)
        || input.agent_token.len() < 20
        || input.agent_token.len() > 200
        || input.code.len() > 200
    {
        return Err(LinkError::new(400, "invalid_request"));
    }
    let mut tx = pool.begin().await?;
    let request: Option<(Uuid, String, bool)> = sqlx::query_as(
        "SELECT user_id,code_hash,(used_at IS NULL AND expires_at>now()) FROM todo_agent_link_requests WHERE id=$1 FOR UPDATE",
    )
    .bind(input.request_id)
    .fetch_optional(&mut *tx)
    .await?;
    let Some((owner, code_hash, open)) = request else {
        return Err(LinkError::new(403, "request_invalid"));
    };
    if !open || code_hash != hash(&input.code) {
        return Err(LinkError::new(403, "request_invalid"));
    }
    // Same Identity UUID *and* same e-mail, and the account must still be enabled.
    let account: Option<String> =
        sqlx::query_scalar("SELECT email FROM identity_users WHERE id=$1 AND enabled")
            .bind(owner)
            .fetch_optional(&mut *tx)
            .await?;
    let matches_account = account.as_deref().is_some_and(|email| {
        owner == input.identity_user_id
            && !email.trim().is_empty()
            && email.trim().eq_ignore_ascii_case(input.email.trim())
    });
    if !matches_account {
        return Err(LinkError::new(403, "account_mismatch"));
    }
    sqlx::query("UPDATE todo_agent_link_requests SET used_at=now() WHERE id=$1")
        .bind(input.request_id)
        .execute(&mut *tx)
        .await?;
    let inbound = random_token(INBOUND_PREFIX);
    sqlx::query("INSERT INTO todo_agent_links(user_id,email_at_link,inbound_token_hash,outbound_token_enc,status) VALUES($1,$2,$3,$4,'active') ON CONFLICT(user_id) DO UPDATE SET email_at_link=EXCLUDED.email_at_link,inbound_token_hash=EXCLUDED.inbound_token_hash,outbound_token_enc=EXCLUDED.outbound_token_enc,status='active',last_seen_at=NULL,created_at=now(),revoked_at=NULL")
        .bind(owner)
        .bind(account.unwrap_or_default().trim().to_lowercase())
        .bind(hash(&inbound))
        .bind(seal(config, owner, &input.agent_token)?)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    Ok(inbound)
}

#[derive(Debug)]
pub struct AgentLink {
    pub user_id: Uuid,
    pub email: String,
}

/// Authenticate an Agent request by its `tdi_` token (hash lookup; link active; account enabled
/// and still carrying the e-mail it was linked with).
pub async fn authenticate(pool: &PgPool, authorization: Option<&str>) -> LinkResult<AgentLink> {
    let unauthorized = || LinkError::new(401, "invalid_token");
    let token = authorization
        .and_then(|value| value.strip_prefix("Bearer "))
        .filter(|token| token.starts_with(INBOUND_PREFIX) && token.len() <= 200)
        .ok_or_else(unauthorized)?;
    let row: Option<(Uuid, String)> = sqlx::query_as(
        "SELECT l.user_id,l.email_at_link FROM todo_agent_links l JOIN identity_users u ON u.id=l.user_id WHERE l.inbound_token_hash=$1 AND l.status='active' AND u.enabled AND lower(u.email)=lower(l.email_at_link)",
    )
    .bind(hash(token))
    .fetch_optional(pool)
    .await?;
    let (user_id, email) = row.ok_or_else(unauthorized)?;
    sqlx::query("UPDATE todo_agent_links SET last_seen_at=now() WHERE user_id=$1 AND (last_seen_at IS NULL OR last_seen_at<now()-interval '1 minute')")
        .bind(user_id)
        .execute(pool)
        .await?;
    Ok(AgentLink { user_id, email })
}

type StatusRow = (
    String,
    String,
    chrono::DateTime<chrono::Utc>,
    Option<chrono::DateTime<chrono::Utc>>,
);

pub async fn status(pool: &PgPool, config: &AgentLinkConfig, user: Uuid) -> LinkResult<Value> {
    let row: Option<StatusRow> =
        sqlx::query_as("SELECT status,email_at_link,created_at,last_seen_at FROM todo_agent_links WHERE user_id=$1")
            .bind(user)
            .fetch_optional(pool)
            .await?;
    Ok(match row {
        Some((status, email, linked_at, last_seen_at)) if status == "active" => json!({
            "enabled": config.enabled, "linked": true, "email": email,
            "linked_at": linked_at, "last_seen_at": last_seen_at,
        }),
        _ => json!({"enabled": config.enabled, "linked": false}),
    })
}

/// Revoke the link. Returns the plaintext `agi_` token when a live link was revoked so the
/// caller can tell the Agent; the stored copy is wiped either way.
pub async fn revoke(
    pool: &PgPool,
    config: &AgentLinkConfig,
    user: Uuid,
) -> LinkResult<Option<String>> {
    let mut tx = pool.begin().await?;
    let row: Option<(String, String)> = sqlx::query_as(
        "SELECT status,outbound_token_enc FROM todo_agent_links WHERE user_id=$1 FOR UPDATE",
    )
    .bind(user)
    .fetch_optional(&mut *tx)
    .await?;
    let Some((status, sealed)) = row else {
        return Ok(None);
    };
    if status != "active" {
        return Ok(None);
    }
    let token = unseal(config, user, &sealed).ok();
    sqlx::query("UPDATE todo_agent_links SET status='revoked',revoked_at=now(),outbound_token_enc='' WHERE user_id=$1")
        .bind(user)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    Ok(token)
}

pub async fn is_linked(pool: &PgPool, user: Uuid) -> LinkResult<bool> {
    Ok(sqlx::query_scalar::<_, bool>(
        "SELECT EXISTS(SELECT 1 FROM todo_agent_links WHERE user_id=$1 AND status='active')",
    )
    .bind(user)
    .fetch_one(pool)
    .await?)
}

/// Agent catalog (`catalog.v1`): the user's Agent workspaces and the repos inside them.
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize, PartialEq)]
pub struct CatalogRepo {
    pub id: String,
    pub name: String,
    pub full_name: String,
    pub has_main: bool,
}
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize, PartialEq)]
pub struct CatalogWorkspace {
    pub id: String,
    pub name: String,
    pub repos: Vec<CatalogRepo>,
}
#[derive(Debug, Clone, Default, serde::Serialize, serde::Deserialize, PartialEq)]
pub struct Catalog {
    pub workspaces: Vec<CatalogWorkspace>,
}
const MAX_CATALOG_BYTES: u64 = 1 << 20;
const MAX_CATALOG_ITEMS: usize = 500;

impl Catalog {
    /// Shape/size checks on what the Agent sent, so a bad or hostile reply can't reach the UI or DB.
    pub fn validated(self) -> Option<Self> {
        let ok = self.workspaces.len() <= MAX_CATALOG_ITEMS
            && self.workspaces.iter().all(|w| {
                crate::domain::valid_agent_id(&w.id)
                    && w.name.chars().count() <= 255
                    && w.repos.len() <= MAX_CATALOG_ITEMS
                    && w.repos.iter().all(|r| {
                        crate::domain::valid_agent_id(&r.id)
                            && r.name.chars().count() <= 255
                            && r.full_name.chars().count() <= 255
                    })
            });
        ok.then_some(self)
    }
    pub fn workspace(&self, id: &str) -> Option<&CatalogWorkspace> {
        self.workspaces.iter().find(|w| w.id == id)
    }
}

/// `GET /api/v1/integrations/todo/catalog` on the Agent with the stored `agi_` token.
pub async fn fetch_catalog(
    pool: &PgPool,
    config: &AgentLinkConfig,
    http: &reqwest::Client,
    user: Uuid,
) -> LinkResult<Catalog> {
    let row: Option<(String, String)> =
        sqlx::query_as("SELECT status,outbound_token_enc FROM todo_agent_links WHERE user_id=$1")
            .bind(user)
            .fetch_optional(pool)
            .await?;
    let sealed = match row {
        Some((status, sealed)) if status == "active" => sealed,
        _ => return Err(LinkError::new(409, "not_linked")),
    };
    let token = unseal(config, user, &sealed)?;
    let unavailable = || LinkError::new(502, "agent_unavailable");
    let url = format!("{}/api/v1/integrations/todo/catalog", config.agent_base_url);
    let response = http
        .get(url)
        .bearer_auth(token)
        .send()
        .await
        .map_err(|error| {
            tracing::warn!(%error, "Agent catalog request failed");
            unavailable()
        })?;
    if response.status() == reqwest::StatusCode::UNAUTHORIZED {
        return Err(LinkError::new(409, "agent_link_rejected"));
    }
    if !response.status().is_success()
        || response
            .content_length()
            .is_some_and(|n| n > MAX_CATALOG_BYTES)
    {
        return Err(unavailable());
    }
    let bytes = response.bytes().await.map_err(|_| unavailable())?;
    if bytes.len() as u64 > MAX_CATALOG_BYTES {
        return Err(unavailable());
    }
    serde_json::from_slice::<Catalog>(&bytes)
        .ok()
        .and_then(Catalog::validated)
        .ok_or_else(unavailable)
}

/// Best-effort `DELETE /api/v1/integrations/todo/link` on the Agent. Returns whether it was told.
pub async fn notify_agent_unlinked(
    http: &reqwest::Client,
    config: &AgentLinkConfig,
    agent_token: &str,
) -> bool {
    let url = format!("{}/api/v1/integrations/todo/link", config.agent_base_url);
    match http.delete(url).bearer_auth(agent_token).send().await {
        Ok(response) => response.status().is_success(),
        Err(error) => {
            tracing::warn!(%error, "Could not tell Agent about the unlink");
            false
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    fn config() -> AgentLinkConfig {
        AgentLinkConfig::new("https://agent.example", "https://agent.example/", [7; 32])
    }
    #[test]
    fn sealed_token_is_random_and_bound_to_user_and_key() {
        let (alice, bob) = (Uuid::new_v4(), Uuid::new_v4());
        let sealed = seal(&config(), alice, "agi_secret").unwrap();
        assert!(!sealed.contains("agi_secret"));
        assert_ne!(sealed, seal(&config(), alice, "agi_secret").unwrap());
        assert_eq!(unseal(&config(), alice, &sealed).unwrap(), "agi_secret");
        assert!(unseal(&config(), bob, &sealed).is_err());
        let other = AgentLinkConfig::new("https://agent.example", "https://agent.example", [9; 32]);
        assert!(unseal(&other, alice, &sealed).is_err());
    }
    #[test]
    fn disabled_config_refuses_crypto() {
        assert_eq!(
            seal(&AgentLinkConfig::default(), Uuid::nil(), "x")
                .unwrap_err()
                .status,
            503
        );
    }
    #[test]
    fn tokens_have_prefix_and_hash_is_stable() {
        let token = random_token(INBOUND_PREFIX);
        assert!(token.starts_with("tdi_") && token.len() > 40);
        assert_ne!(token, random_token(INBOUND_PREFIX));
        assert_eq!(hash("a"), hash("a"));
        assert_ne!(hash("a"), hash("b"));
    }
}
