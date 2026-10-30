use app_config::ConfigSource;

pub struct IdentitySeedConfig {
    pub portal_url: String,
    pub redirect_uri: String,
}
impl IdentitySeedConfig {
    pub fn from_source(source: &ConfigSource) -> Self {
        let portal_url = source
            .optional("IDENTITY_CORS_ORIGIN")
            .unwrap_or("https://identity.nvnhan0810.com")
            .to_owned();
        let redirect_uri = source
            .optional("IDENTITY_REDIRECT_URI")
            .map(str::to_owned)
            .unwrap_or_else(|| format!("{}/auth/callback", portal_url.trim_end_matches('/')));
        Self {
            portal_url,
            redirect_uri,
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn explicit_redirect_overrides_derived_callback() {
        let config = IdentitySeedConfig::from_source(&ConfigSource::from_values([
            (
                "IDENTITY_CORS_ORIGIN".into(),
                "http://localhost:5174".into(),
            ),
            (
                "IDENTITY_REDIRECT_URI".into(),
                "http://localhost:5174/sso/callback".into(),
            ),
        ]));
        assert_eq!(config.redirect_uri, "http://localhost:5174/sso/callback");
        let fallback = IdentitySeedConfig::from_source(&ConfigSource::from_values([(
            "IDENTITY_CORS_ORIGIN".into(),
            "http://localhost:5174/".into(),
        )]));
        assert_eq!(fallback.redirect_uri, "http://localhost:5174/auth/callback");
    }
}
