use std::{error::Error, fmt};

#[derive(Debug, Clone, PartialEq, Eq)]
pub enum ClientError {
    InvalidClientId,
    InvalidScope,
    InvalidClientName,
    InvalidRedirectUri,
    EmptyRedirectUris,
    DuplicateRedirectUri,
    DisabledClient,
    RedirectUriNotAllowed,
    InvalidSecretHash,
    InvalidCredentialForClientType,
    VersionOverflow,
}

impl fmt::Display for ClientError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "{self:?}")
    }
}

impl Error for ClientError {}
