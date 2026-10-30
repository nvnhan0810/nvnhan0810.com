#[derive(Debug, thiserror::Error)]
#[error("{code}")]
pub struct IdentityError {
    pub code: &'static str,
    pub status: u16,
}

impl IdentityError {
    pub fn bad(code: &'static str) -> Self {
        Self { code, status: 400 }
    }
    pub fn unauthorized() -> Self {
        Self {
            code: "invalid_client",
            status: 401,
        }
    }
    pub fn forbidden() -> Self {
        Self {
            code: "access_denied",
            status: 403,
        }
    }
    pub fn internal() -> Self {
        Self {
            code: "server_error",
            status: 500,
        }
    }
}
pub type Result<T> = std::result::Result<T, IdentityError>;
