use super::ConfigSource;

pub struct IdentityMasterDataConfig {
    pub portal_url: String,
}
impl IdentityMasterDataConfig {
    pub fn from_source(source: &ConfigSource) -> Self {
        Self {
            portal_url: source
                .optional("IDENTITY_CORS_ORIGIN")
                .unwrap_or("https://identity.nvnhan0810.com")
                .to_owned(),
        }
    }
}
