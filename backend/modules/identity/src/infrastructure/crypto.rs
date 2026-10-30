use base64::{Engine, engine::general_purpose::URL_SAFE_NO_PAD};
use rand::RngCore;
use sha2::{Digest, Sha256};
use subtle::ConstantTimeEq;

pub fn random_token() -> String {
    let mut bytes = [0u8; 32];
    rand::rngs::OsRng.fill_bytes(&mut bytes);
    URL_SAFE_NO_PAD.encode(bytes)
}

pub fn hash(value: &str) -> String {
    URL_SAFE_NO_PAD.encode(Sha256::digest(value.as_bytes()))
}

pub fn equal(left: &str, right: &str) -> bool {
    bool::from(left.as_bytes().ct_eq(right.as_bytes()))
}

pub fn valid_verifier(value: &str) -> bool {
    (43..=128).contains(&value.len())
        && value
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-._~".contains(&b))
}

use crate::application::{
    config::IdentityConfig,
    error::{IdentityError, Result},
    models::AccessClaims,
    ports::TokenCrypto,
};
use jsonwebtoken::{Algorithm, DecodingKey, EncodingKey, Header, Validation, decode, encode};

pub struct JwtCrypto {
    encoding: EncodingKey,
    decoding: DecodingKey,
    issuer: String,
    key_id: String,
}
impl JwtCrypto {
    pub fn new(config: &IdentityConfig, private: &[u8], public: &[u8]) -> Result<Self> {
        let encoding = EncodingKey::from_rsa_pem(private).map_err(|_| IdentityError::internal())?;
        let decoding = DecodingKey::from_rsa_pem(public).map_err(|_| IdentityError::internal())?;
        // Fail startup if the mounted pair does not match.
        let sample = serde_json::json!({"exp":chrono::Utc::now().timestamp()+60});
        let token = encode(&Header::new(Algorithm::RS256), &sample, &encoding)
            .map_err(|_| IdentityError::internal())?;
        decode::<serde_json::Value>(&token, &decoding, &Validation::new(Algorithm::RS256))
            .map_err(|_| IdentityError::internal())?;
        Ok(Self {
            encoding,
            decoding,
            issuer: config.app_url.clone(),
            key_id: config.key_id.clone(),
        })
    }
}
impl TokenCrypto for JwtCrypto {
    fn random_token(&self) -> String {
        random_token()
    }
    fn hash(&self, value: &str) -> String {
        hash(value)
    }
    fn equal(&self, left: &str, right: &str) -> bool {
        equal(left, right)
    }
    fn valid_verifier(&self, value: &str) -> bool {
        valid_verifier(value)
    }
    fn sign(&self, claims: &AccessClaims) -> Result<String> {
        let mut header = Header::new(Algorithm::RS256);
        header.kid = Some(self.key_id.clone());
        header.typ = Some("at+jwt".into());
        encode(&header, claims, &self.encoding).map_err(|_| IdentityError::internal())
    }
    fn verify(&self, token: &str) -> Result<AccessClaims> {
        let mut validation = Validation::new(Algorithm::RS256);
        validation.set_issuer(&[&self.issuer]);
        validation.set_audience(&[&self.issuer]);
        validation.set_required_spec_claims(&["iss", "sub", "aud", "exp"]);
        validation.leeway = 5;
        let data = decode::<AccessClaims>(token, &self.decoding, &validation)
            .map_err(|_| IdentityError::unauthorized())?;
        if data.header.kid.as_deref() != Some(&self.key_id)
            || data.header.typ.as_deref() != Some("at+jwt")
        {
            return Err(IdentityError::unauthorized());
        }
        Ok(data.claims)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn rfc7636_challenge() {
        assert_eq!(
            hash("dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"),
            "E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM"
        );
        assert!(valid_verifier(
            "dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"
        ));
        assert!(!valid_verifier("short"));
    }
    #[test]
    fn random_tokens_have_enough_entropy_and_are_distinct() {
        let a = random_token();
        assert_eq!(a.len(), 43);
        assert_ne!(a, random_token());
        assert!(equal(&hash(&a), &hash(&a)));
        assert!(!equal(&a, &random_token()));
    }
}
