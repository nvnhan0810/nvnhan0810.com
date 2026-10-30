#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum AuthorizationError {
    InvalidRequest,
    InvalidScope,
}
