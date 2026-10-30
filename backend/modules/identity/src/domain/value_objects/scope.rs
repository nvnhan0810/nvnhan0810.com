use crate::domain::errors::client_error::ClientError;

#[derive(Debug, Clone, PartialEq, Eq, Hash)]
pub struct Scope(String);

impl Scope {
    pub fn parse(value: impl Into<String>) -> Result<Self, ClientError> {
        let value = value.into();
        // OAuth scope-token: printable ASCII excluding space, quote and backslash.
        if value.is_empty()
            || value.len() > 128
            || !value
                .bytes()
                .all(|b| b == 0x21 || (0x23..=0x5b).contains(&b) || (0x5d..=0x7e).contains(&b))
        {
            return Err(ClientError::InvalidScope);
        }
        Ok(Self(value))
    }
    pub fn as_str(&self) -> &str {
        &self.0
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn accepts_application_defined_scope_tokens() {
        assert!(Scope::parse("new-app:reports.read").is_ok());
        for value in [
            "",
            "read write",
            "read\n",
            "read\\write",
            "read\"write",
            "đọc",
        ] {
            assert!(Scope::parse(value).is_err());
        }
    }
}
