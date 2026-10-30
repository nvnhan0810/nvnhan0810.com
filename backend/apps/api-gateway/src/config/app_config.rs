use super::{ConfigSource, DatabaseConfig};
use axum::http::HeaderValue;
use identity::application::config::IdentityConfig;
use std::path::PathBuf;

pub struct AppConfig {
    pub database: DatabaseConfig,
    pub identity: IdentityConfig,
    pub private_key_path: PathBuf,
    pub public_key_path: PathBuf,
    pub admin_cors_origin: Option<HeaderValue>,
    pub production: bool,
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
        let admin_cors_origin = source
            .optional("IDENTITY_CORS_ORIGIN")
            .map(str::trim)
            .filter(|origin| !origin.is_empty())
            .map(|origin| {
                let parsed = identity::domain::value_objects::WebOrigin::parse(origin)
                    .map_err(|_| "IDENTITY_CORS_ORIGIN must be one exact HTTP(S) origin")?;
                if production && !parsed.is_https() {
                    return Err("IDENTITY_CORS_ORIGIN must use HTTPS in production");
                }
                origin.parse().map_err(|_| "Invalid CORS origin header")
            })
            .transpose()?;
        Ok(Self {
            database: DatabaseConfig::from_source(source)?,
            identity,
            admin_cors_origin,
            production,
            private_key_path: source.required("IDENTITY_PRIVATE_KEY_PATH")?.into(),
            public_key_path: source.required("IDENTITY_PUBLIC_KEY_PATH")?.into(),
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    fn source_with(environment: Option<&str>, app_url: &str) -> ConfigSource {
        source_with_cors(environment, app_url, None)
    }
    fn source_with_cors(
        environment: Option<&str>,
        app_url: &str,
        cors: Option<&str>,
    ) -> ConfigSource {
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
        if let Some(cors) = cors {
            values.push(("IDENTITY_CORS_ORIGIN".into(), cors.into()));
        }
        ConfigSource::from_values(values)
    }
    #[test]
    fn startup_needs_no_email_allowlists_or_permission_catalog() {
        let source = source_with(None, "https://sso.example");
        let config = AppConfig::from_source(&source).unwrap();
        assert_eq!(config.identity.app_url, "https://sso.example");
        assert!(config.admin_cors_origin.is_none());
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
    fn admin_cors_accepts_one_origin_and_enforces_environment() {
        let make = |env, origin| {
            AppConfig::from_source(&source_with_cors(
                Some(env),
                "https://api.example",
                Some(origin),
            ))
        };
        assert_eq!(
            make("production", "https://identity.example")
                .unwrap()
                .admin_cors_origin
                .unwrap(),
            "https://identity.example"
        );
        assert!(make("local", "http://localhost:5174").is_ok());
        for origin in [
            "http://localhost:5174",
            "https://a.example,https://b.example",
            "*",
            "null",
            "https://identity.example/",
            "https://identity.example/path",
        ] {
            assert!(make("production", origin).is_err());
        }
        assert!(make("production", "").unwrap().admin_cors_origin.is_none());
    }
    #[test]
    fn migrations_only_need_database_configuration() {
        let source = ConfigSource::from_values(
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
