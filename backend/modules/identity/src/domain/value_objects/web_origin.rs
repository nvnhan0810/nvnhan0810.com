use super::RedirectUri;
use crate::domain::errors::client_error::ClientError;

/// Exact browser origin: scheme + host + optional non-default port.
#[derive(Clone, Debug, PartialEq, Eq)]
pub struct WebOrigin(String);
impl WebOrigin {
    pub fn parse(value: &str) -> Result<Self, ClientError> {
        RedirectUri::parse(value)?;
        let url = url::Url::parse(value).map_err(|_| ClientError::InvalidRedirectUri)?;
        if !matches!(url.scheme(), "http" | "https") || url.origin().ascii_serialization() != value
        {
            return Err(ClientError::InvalidRedirectUri);
        }
        Ok(Self(value.to_owned()))
    }
    pub fn as_str(&self) -> &str {
        &self.0
    }
    pub fn is_https(&self) -> bool {
        self.0.starts_with("https://")
    }
}
