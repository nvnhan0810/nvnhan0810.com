use super::ConfigSource;
use sqlx::postgres::PgConnectOptions;

/// No Debug implementation: never render database credentials in logs.
pub struct DatabaseConfig {
    host: String,
    port: u16,
    name: String,
    username: String,
    password: String,
}

impl DatabaseConfig {
    pub fn from_source(source: &ConfigSource) -> Result<Self, String> {
        let port = source
            .required("DB_PORT")?
            .parse::<u16>()
            .ok()
            .filter(|port| *port != 0)
            .ok_or("DB_PORT must be between 1 and 65535")?;
        Ok(Self {
            host: source.required("DB_HOST")?,
            port,
            name: source.required("DB_NAME")?,
            username: source.required("DB_USERNAME")?,
            // Empty passwords are allowed for local PostgreSQL auth.
            password: source
                .optional("DB_PASSWORD")
                .ok_or("Missing configuration: DB_PASSWORD")?
                .to_owned(),
        })
    }

    pub fn connect_options(&self) -> PgConnectOptions {
        // Driver setters accept the raw password; no URL interpolation/encoding.
        PgConnectOptions::new()
            .host(&self.host)
            .port(self.port)
            .database(&self.name)
            .username(&self.username)
            .password(&self.password)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    fn source(port: &str, password: &str) -> ConfigSource {
        ConfigSource::from_values(
            [
                ("DB_HOST", "postgres.internal"),
                ("DB_PORT", port),
                ("DB_NAME", "identity"),
                ("DB_USERNAME", "identity-user"),
                ("DB_PASSWORD", password),
            ]
            .map(|(k, v)| (k.into(), v.into())),
        )
    }
    #[test]
    fn raw_credentials_and_driver_options_are_preserved() {
        let config = DatabaseConfig::from_source(&source("5433", "a@b:/?#% &+$")).unwrap();
        assert_eq!(config.password, "a@b:/?#% &+$");
        let options = config.connect_options();
        assert_eq!(options.get_host(), "postgres.internal");
        assert_eq!(options.get_port(), 5433);
        assert_eq!(options.get_database(), Some("identity"));
        assert_eq!(options.get_username(), "identity-user");
        assert!(DatabaseConfig::from_source(&source("5432", "")).is_ok());
    }
    #[test]
    fn malformed_ports_and_missing_fields_fail_at_startup() {
        for port in ["0", "65536", "abc", "-1"] {
            assert!(DatabaseConfig::from_source(&source(port, "fixture")).is_err());
        }
        assert!(DatabaseConfig::from_source(&ConfigSource::from_values([])).is_err());
    }
}
