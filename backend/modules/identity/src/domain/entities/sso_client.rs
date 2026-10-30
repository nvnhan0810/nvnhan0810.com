use crate::domain::{
    errors::client_error::ClientError,
    value_objects::{ClientId, ClientName, ClientSecretHash, ClientType, RedirectUri, SsoClientId},
};
use std::collections::HashSet;

#[derive(Debug, Clone)]
pub struct SsoClient {
    id: SsoClientId,
    client_id: ClientId,
    name: ClientName,
    client_type: ClientType,
    redirect_uris: Vec<RedirectUri>,
    secret_hash: Option<ClientSecretHash>,
    enabled: bool,
    version: u64,
}

impl SsoClient {
    pub fn register(
        id: SsoClientId,
        client_id: ClientId,
        name: ClientName,
        client_type: ClientType,
        redirect_uris: Vec<RedirectUri>,
        secret_hash: Option<ClientSecretHash>,
    ) -> Result<Self, ClientError> {
        Self::validate_redirects(&redirect_uris)?;
        match (client_type, secret_hash.is_some()) {
            (ClientType::Public, false) | (ClientType::Confidential, true) => {}
            _ => return Err(ClientError::InvalidCredentialForClientType),
        }

        Ok(Self {
            id,
            client_id,
            name,
            client_type,
            redirect_uris,
            secret_hash,
            enabled: true,
            version: 1,
        })
    }

    pub fn id(&self) -> SsoClientId {
        self.id
    }
    pub fn client_id(&self) -> &ClientId {
        &self.client_id
    }
    pub fn name(&self) -> &ClientName {
        &self.name
    }
    pub fn client_type(&self) -> ClientType {
        self.client_type
    }
    pub fn redirect_uris(&self) -> &[RedirectUri] {
        &self.redirect_uris
    }
    pub fn is_enabled(&self) -> bool {
        self.enabled
    }
    pub fn version(&self) -> u64 {
        self.version
    }

    pub(crate) fn secret_hash(&self) -> Option<&ClientSecretHash> {
        self.secret_hash.as_ref()
    }

    pub fn update_details(
        &mut self,
        name: ClientName,
        redirect_uris: Vec<RedirectUri>,
    ) -> Result<(), ClientError> {
        Self::validate_redirects(&redirect_uris)?;
        if self.name == name && self.redirect_uris == redirect_uris {
            return Ok(());
        }
        let next = self.next_version()?;
        self.name = name;
        self.redirect_uris = redirect_uris;
        self.version = next;
        Ok(())
    }

    pub fn set_enabled(&mut self, enabled: bool) -> Result<(), ClientError> {
        if self.enabled == enabled {
            return Ok(());
        }
        let next = self.next_version()?;
        self.enabled = enabled;
        self.version = next;
        Ok(())
    }

    pub fn rotate_secret(&mut self, secret_hash: ClientSecretHash) -> Result<(), ClientError> {
        if self.client_type != ClientType::Confidential {
            return Err(ClientError::InvalidCredentialForClientType);
        }
        let next = self.next_version()?;
        self.secret_hash = Some(secret_hash);
        self.version = next;
        Ok(())
    }

    pub fn ensure_can_authorize(&self, redirect_uri: &str) -> Result<(), ClientError> {
        if !self.enabled {
            return Err(ClientError::DisabledClient);
        }
        if !self
            .redirect_uris
            .iter()
            .any(|uri| uri.as_str() == redirect_uri)
        {
            return Err(ClientError::RedirectUriNotAllowed);
        }
        Ok(())
    }

    fn validate_redirects(uris: &[RedirectUri]) -> Result<(), ClientError> {
        if uris.is_empty() {
            return Err(ClientError::EmptyRedirectUris);
        }
        let mut seen = HashSet::new();
        if uris.iter().any(|uri| !seen.insert(uri)) {
            return Err(ClientError::DuplicateRedirectUri);
        }
        Ok(())
    }

    fn next_version(&self) -> Result<u64, ClientError> {
        self.version
            .checked_add(1)
            .ok_or(ClientError::VersionOverflow)
    }
}
