#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum GrantError {
    Expired,
    Revoked,
}
