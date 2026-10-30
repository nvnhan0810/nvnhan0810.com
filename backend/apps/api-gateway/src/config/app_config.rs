use super::{ConfigSource, DatabaseConfig};
use axum::http::HeaderValue;
use identity::application::config::IdentityConfig;
use std::path::PathBuf;

pub struct AppConfig {
    pub database: DatabaseConfig,
    pub identity: IdentityConfig,
    pub private_key_path: PathBuf,
    pub public_key_path: PathBuf,
    pub cors_origins: Vec<HeaderValue>,
}

impl AppConfig {
    pub fn from_source(source: &ConfigSource) -> Result<Self, String> {
        let production = match source.optional("APP_ENV").unwrap_or("production") {
            "production" => true,
            "local" | "development" => false,
            _ => return Err("APP_ENV must be local, development or production".into()),
        };
        let app_url = source.required("APP_URL")?.trim_end_matches('/').to_owned();
        let uri = url::Url::parse(&app_url).map_err(|_| "Invalid APP_URL")?;
        if !matches!(uri.scheme(), "http" | "https")
            || (production && uri.scheme() != "https")
            || uri.host_str().is_none()
            || uri.path() != "/"
            || uri.query().is_some()
            || uri.fragment().is_some()
            || !uri.username().is_empty()
            || uri.password().is_some()
        {
            return Err("APP_URL must be an HTTP(S) origin; production requires HTTPS".into());
        }
        let identity = IdentityConfig {
            app_url,
            google_client_id: source.required("GOOGLE_CLIENT_ID")?,
            google_client_secret: source.required("GOOGLE_CLIENT_SECRET")?,
            key_id: source.required("IDENTITY_KEY_ID")?,
            access_ttl: 900,
            refresh_ttl: 30 * 24 * 3600,
        };
        let cors_origins = source
            .optional("IDENTITY_CORS_ORIGINS")
            .unwrap_or_default()
            .split(',')
            .map(str::trim)
            .filter(|v| !v.is_empty())
            .map(|origin| {
                let uri = url::Url::parse(origin).map_err(|_| "Invalid CORS origin")?;
                if !matches!(uri.scheme(), "https" | "http")
                    || (production && uri.scheme() != "https")
                    || uri.host_str().is_none()
                    || uri.origin().ascii_serialization() != origin
                {
                    return Err(
                        "CORS origins must be exact HTTP(S) origins; production requires HTTPS",
                    );
                }
                origin.parse().map_err(|_| "Invalid CORS origin header")
            })
            .collect::<Result<Vec<HeaderValue>, _>>()?;
        Ok(Self {
            database: DatabaseConfig::from_source(source)?,
            identity,
            cors_origins,
            private_key_path: source.required("IDENTITY_PRIVATE_KEY_PATH")?.into(),
            public_key_path: source.required("IDENTITY_PUBLIC_KEY_PATH")?.into(),
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    fn source_with(environment: Option<&str>, app_url: &str) -> ConfigSource {
        let mut values: Vec<(String, String)> = [
            ("DB_HOST", "127.0.0.1"),
            ("DB_PORT", "5432"),
            ("DB_NAME", "identity"),
            ("DB_USERNAME", "identity"),
            ("DB_PASSWORD", "fixture"),
            ("APP_URL", app_url),
            ("GOOGLE_CLIENT_ID", "fixture"),
            ("GOOGLE_CLIENT_SECRET", "fixture"),
            ("IDENTITY_KEY_ID", "key"),
            ("IDENTITY_PRIVATE_KEY_PATH", "private.pem"),
            ("IDENTITY_PUBLIC_KEY_PATH", "public.pem"),
        ]
        .map(|(k, v)| (k.into(), v.into()))
        .into_iter()
        .collect();
        if let Some(environment) = environment {
            values.push(("APP_ENV".into(), environment.into()));
        }
        ConfigSource::fixture(values)
    }
    #[test]
    fn startup_needs_no_email_allowlists_or_permission_catalog() {
        let source = source_with(None, "https://sso.example");
        let config = AppConfig::from_source(&source).unwrap();
        assert_eq!(config.identity.app_url, "https://sso.example");
        assert!(config.cors_origins.is_empty());
    }
    #[test]
    fn local_http_is_explicit_and_production_never_accepts_it() {
        for environment in [Some("local"), Some("development")] {
            assert!(
                AppConfig::from_source(&source_with(environment, "http://localhost:8080")).is_ok()
            );
        }
        for environment in [None, Some("production"), Some("typo")] {
            assert!(
                AppConfig::from_source(&source_with(environment, "http://localhost:8080")).is_err()
            );
        }
        assert!(
            AppConfig::from_source(&source_with(Some("production"), "https://api.example")).is_ok()
        );
        assert!(
            AppConfig::from_source(&source_with(Some("local"), "http://localhost:8080/path"))
                .is_err()
        );
    }
    #[test]
    fn migrations_only_need_database_configuration() {
        let source = ConfigSource::fixture(
            [
                ("DB_HOST", "127.0.0.1"),
                ("DB_PORT", "5432"),
                ("DB_NAME", "identity"),
                ("DB_USERNAME", "identity"),
                ("DB_PASSWORD", ""),
            ]
            .map(|(k, v)| (k.into(), v.into())),
        );
        assert!(DatabaseConfig::from_source(&source).is_ok());
        assert!(AppConfig::from_source(&source).is_err());
    }
}
