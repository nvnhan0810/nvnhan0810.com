use crate::domain::errors::client_error::ClientError;

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct ClientName(String);

impl ClientName {
    pub fn parse(value: impl Into<String>) -> Result<Self, ClientError> {
        let value = value.into();
        let value = value.trim();
        if value.is_empty() || value.chars().count() > 255 || value.chars().any(char::is_control) {
            return Err(ClientError::InvalidClientName);
        }
        Ok(Self(value.to_owned()))
    }

    pub fn as_str(&self) -> &str {
        &self.0
    }
}
