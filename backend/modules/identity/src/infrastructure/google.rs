use jsonwebtoken::{Algorithm, DecodingKey, Validation, decode, decode_header};
use serde::Deserialize;
use std::time::{Duration, Instant};
use tokio::sync::RwLock;

use crate::application::{
    config::IdentityConfig,
    error::{IdentityError, Result},
    models::GoogleIdentity,
    ports::{GoogleIdentityProvider, PortFuture},
};

#[derive(Clone, Deserialize)]
struct GoogleJwk {
    kid: String,
    kty: String,
    alg: Option<String>,
    n: String,
    e: String,
}
#[derive(Clone, Deserialize)]
struct GoogleKeys {
    keys: Vec<GoogleJwk>,
}

pub struct GoogleProvider {
    config: IdentityConfig,
    http: reqwest::Client,
    keys: RwLock<Option<(Instant, GoogleKeys)>>,
}

impl GoogleProvider {
    pub fn new(config: IdentityConfig) -> Result<Self> {
        let http = reqwest::Client::builder()
            .timeout(Duration::from_secs(15))
            .redirect(reqwest::redirect::Policy::none())
            .build()
            .map_err(|_| IdentityError::internal())?;
        Ok(Self {
            config,
            http,
            keys: RwLock::new(None),
        })
    }

    async fn signing_key(&self, kid: &str) -> Result<DecodingKey> {
        // Refresh on expiry; a missing kid in a fresh cache is rejected to avoid
        // turning arbitrary invalid callbacks into unbounded Google requests.
        let mut cache = self.keys.write().await;
        if cache
            .as_ref()
            .is_none_or(|(time, _)| time.elapsed() > Duration::from_secs(300))
        {
            let keys = self
                .http
                .get("https://www.googleapis.com/oauth2/v3/certs")
                .send()
                .await
                .map_err(|_| IdentityError::internal())?
                .error_for_status()
                .map_err(|_| IdentityError::internal())?
                .json::<GoogleKeys>()
                .await
                .map_err(|_| IdentityError::internal())?;
            *cache = Some((Instant::now(), keys));
        }
        let key = cache
            .as_ref()
            .and_then(|(_, keys)| {
                keys.keys.iter().find(|k| {
                    k.kid == kid && k.kty == "RSA" && k.alg.as_deref().is_none_or(|a| a == "RS256")
                })
            })
            .ok_or_else(|| IdentityError::bad("invalid_grant"))?;
        DecodingKey::from_rsa_components(&key.n, &key.e)
            .map_err(|_| IdentityError::bad("invalid_grant"))
    }
}

#[derive(Deserialize)]
struct GoogleTokens {
    id_token: String,
}
#[derive(Clone, Deserialize)]
struct GoogleClaims {
    sub: String,
    email: String,
    email_verified: bool,
    name: Option<String>,
    picture: Option<String>,
    nonce: String,
    azp: Option<String>,
}

impl GoogleIdentityProvider for GoogleProvider {
    fn authenticate<'a>(
        &'a self,
        code: &'a str,
        verifier: &'a str,
        nonce: &'a str,
    ) -> PortFuture<'a, GoogleIdentity> {
        Box::pin(async move {
            let redirect = self.config.google_redirect_uri();
            let response = self
                .http
                .post("https://oauth2.googleapis.com/token")
                .form(&[
                    ("grant_type", "authorization_code"),
                    ("client_id", self.config.google_client_id.as_str()),
                    ("client_secret", self.config.google_client_secret.as_str()),
                    ("redirect_uri", redirect.as_str()),
                    ("code", code),
                    ("code_verifier", verifier),
                ])
                .send()
                .await
                .map_err(|_| IdentityError::internal())?
                .error_for_status()
                .map_err(|_| IdentityError::bad("invalid_grant"))?
                .json::<GoogleTokens>()
                .await
                .map_err(|_| IdentityError::bad("invalid_grant"))?;
            let header = decode_header(&response.id_token)
                .map_err(|_| IdentityError::bad("invalid_grant"))?;
            if header.alg != Algorithm::RS256 {
                return Err(IdentityError::bad("invalid_grant"));
            }
            let key = self
                .signing_key(
                    header
                        .kid
                        .as_deref()
                        .ok_or_else(|| IdentityError::bad("invalid_grant"))?,
                )
                .await?;
            let mut validation = Validation::new(Algorithm::RS256);
            validation.set_audience(&[&self.config.google_client_id]);
            validation.set_issuer(&["https://accounts.google.com", "accounts.google.com"]);
            validation.set_required_spec_claims(&["sub", "exp", "iss", "aud"]);
            validation.leeway = 30;
            let claims = decode::<GoogleClaims>(&response.id_token, &key, &validation)
                .map_err(|_| IdentityError::bad("invalid_grant"))?
                .claims;
            if !super::crypto::equal(&claims.nonce, nonce)
                || !claims.email_verified
                || claims.sub.is_empty()
                || claims
                    .azp
                    .as_ref()
                    .is_some_and(|a| a != &self.config.google_client_id)
            {
                return Err(IdentityError::bad("invalid_grant"));
            }
            Ok(GoogleIdentity {
                subject: claims.sub,
                name: claims.name.unwrap_or_else(|| claims.email.clone()),
                email: claims.email.to_lowercase(),
                avatar: claims.picture,
            })
        })
    }
}
