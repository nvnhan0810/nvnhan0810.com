#[derive(Clone)]
pub struct IdentityConfig {
    pub app_url: String,
    pub google_client_id: String,
    pub google_client_secret: String,
    pub key_id: String,
    pub access_ttl: i64,
    pub refresh_ttl: i64,
}

impl IdentityConfig {
    pub fn session_cookie_name(&self) -> &'static str {
        if self.app_url.starts_with("https://") {
            "__Host-sso_session"
        } else {
            "sso_session"
        }
    }

    pub fn attempt_cookie_name(&self, state: &str) -> String {
        let prefix = if self.app_url.starts_with("https://") {
            "__Host-sso_attempt_"
        } else {
            "sso_attempt_"
        };
        format!("{prefix}{state}")
    }

    pub fn cookie_header(&self, name: &str, value: &str, max_age: u32) -> String {
        let secure = if self.app_url.starts_with("https://") {
            "; Secure"
        } else {
            ""
        };
        format!("{name}={value}; Path=/{secure}; HttpOnly; SameSite=Lax; Max-Age={max_age}")
    }

    pub fn google_redirect_uri(&self) -> String {
        format!("{}/auth/google/callback", self.app_url)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    fn config(app_url: &str) -> IdentityConfig {
        IdentityConfig {
            app_url: app_url.into(),
            google_client_id: String::new(),
            google_client_secret: String::new(),
            key_id: String::new(),
            access_ttl: 900,
            refresh_ttl: 900,
        }
    }
    #[test]
    fn http_development_cookies_can_be_set_and_deleted() {
        let config = config("http://localhost:8080");
        assert_eq!(config.session_cookie_name(), "sso_session");
        assert_eq!(config.attempt_cookie_name("state"), "sso_attempt_state");
        let cookie = config.cookie_header(config.session_cookie_name(), "value", 600);
        assert!(!cookie.contains("Secure"));
        assert!(cookie.contains("HttpOnly; SameSite=Lax"));
        assert!(
            config
                .cookie_header(config.session_cookie_name(), "", 0)
                .contains("Max-Age=0")
        );
    }
    #[test]
    fn https_cookies_keep_host_prefix_and_secure_attribute() {
        let config = config("https://api.example");
        assert_eq!(config.session_cookie_name(), "__Host-sso_session");
        assert_eq!(
            config.attempt_cookie_name("state"),
            "__Host-sso_attempt_state"
        );
        assert!(
            config
                .cookie_header(config.session_cookie_name(), "value", 600)
                .contains("; Secure;")
        );
        assert!(
            config
                .cookie_header(config.session_cookie_name(), "", 0)
                .contains("; Secure;")
        );
    }
}
