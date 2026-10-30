use crate::domain::errors::client_error::ClientError;

// Hash đã được SecretHasher tạo; không nhận plaintext secret vào aggregate.
#[derive(Clone)]
pub struct ClientSecretHash(String);

impl ClientSecretHash {
    pub fn from_encoded(value: String) -> Result<Self, ClientError> {
        if value.trim().is_empty() {
            return Err(ClientError::InvalidSecretHash);
        }
        Ok(Self(value))
    }

    // Giới hạn truy cập trong crate cho persistence/verification adapter.
    pub(crate) fn encoded(&self) -> &str {
        &self.0
    }
}

impl std::fmt::Debug for ClientSecretHash {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        f.write_str("ClientSecretHash([REDACTED])")
    }
}
