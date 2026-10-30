use crate::domain::errors::client_error::ClientError;
use url::Url;

#[derive(Debug, Clone, PartialEq, Eq, Hash)]
pub struct RedirectUri(String);

impl RedirectUri {
    pub fn parse(value: impl Into<String>) -> Result<Self, ClientError> {
        let value = value.into();
        if value.len() > 2048
            || value.chars().any(|c| c.is_whitespace() || c.is_control())
            || value.contains('\\')
        {
            return Err(ClientError::InvalidRedirectUri);
        }

        let parsed = Url::parse(&value).map_err(|_| ClientError::InvalidRedirectUri)?;

        let web = parsed.scheme() == "https" && parsed.host_str().is_some();
        let loopback = parsed.scheme() == "http"
            && matches!(parsed.host_str(), Some("127.0.0.1" | "[::1]" | "::1"));
        // Reverse-domain native schemes reduce collision risk. Exact callback
        // matching still applies; no wildcards or dynamic loopback port matching.
        let native = parsed.scheme().contains('.')
            && !matches!(parsed.scheme(), "http" | "https")
            && !parsed.path().is_empty();
        if !(web || loopback || native)
            || !parsed.username().is_empty()
            || parsed.password().is_some()
            || parsed.fragment().is_some()
            || value.contains('*')
        {
            return Err(ClientError::InvalidRedirectUri);
        }

        Ok(Self(value))
    }

    pub fn as_str(&self) -> &str {
        &self.0
    }
}
