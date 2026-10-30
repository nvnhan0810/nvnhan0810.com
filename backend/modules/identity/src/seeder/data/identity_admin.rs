use crate::application::{
    error::{IdentityError, Result},
    models::ClientInput,
};

pub const IDENTITY_ADMIN_CLIENT_ID: &str = "identity";

pub fn identity_admin_input(portal_url: &str, redirect_uri: &str) -> Result<ClientInput> {
    let portal_url = portal_url.trim_end_matches('/');
    let url = url::Url::parse(portal_url)
        .map_err(|_| IdentityError::bad("invalid_IDENTITY_CORS_ORIGIN"))?;
    let loopback = matches!(
        url.host_str(),
        Some("localhost" | "127.0.0.1" | "[::1]" | "::1")
    );
    if !(url.scheme() == "https" || (url.scheme() == "http" && loopback))
        || url.host_str().is_none()
        || url.path() != "/"
        || url.query().is_some()
        || url.fragment().is_some()
        || !url.username().is_empty()
        || url.password().is_some()
    {
        return Err(IdentityError::bad("invalid_IDENTITY_CORS_ORIGIN"));
    }
    let callback = url::Url::parse(redirect_uri)
        .map_err(|_| IdentityError::bad("invalid_identity_redirect_uri"))?;
    if !matches!(callback.scheme(), "http" | "https")
        || crate::domain::value_objects::RedirectUri::parse(redirect_uri).is_err()
    {
        return Err(IdentityError::bad("invalid_identity_redirect_uri"));
    }
    let input = ClientInput {
        client_id: IDENTITY_ADMIN_CLIENT_ID.into(),
        name: "Identity Admin".into(),
        domain: Some(portal_url.into()),
        client_type: "public".into(),
        redirect_uris: vec![redirect_uri.into()],
        scopes: vec!["profile".into(), "identity.clients.manage".into()],
        enabled: true,
    };
    Ok(input)
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn admin_is_a_public_pkce_client_with_the_expected_callback() {
        let input = identity_admin_input(
            "https://identity.nvnhan0810.com/",
            "https://identity.nvnhan0810.com/auth/callback",
        )
        .unwrap();
        assert_eq!(input.client_id, IDENTITY_ADMIN_CLIENT_ID);
        assert_eq!(input.client_type, "public");
        assert_eq!(
            input.redirect_uris,
            ["https://identity.nvnhan0810.com/auth/callback"]
        );
        assert_eq!(input.scopes, ["profile", "identity.clients.manage"]);
        assert!(
            identity_admin_input(
                "http://127.0.0.1:5174",
                "http://127.0.0.1:5174/auth/callback"
            )
            .is_ok()
        );
        assert!(
            identity_admin_input(
                "http://localhost:5174",
                "http://localhost:5174/auth/callback"
            )
            .is_ok()
        );
        for url in [
            "http://evil.example",
            "https://identity.example/path",
            "https://identity.example/?x=1",
            "https://user:pass@identity.example",
        ] {
            assert!(identity_admin_input(url, "https://identity.example/auth/callback").is_err());
        }
    }
}

#[cfg(test)]
mod redirect_tests {
    use super::*;
    #[test]
    fn supplied_callback_is_preserved_and_invalid_callbacks_are_rejected() {
        let origin = "https://identity.example";
        let callback = "https://identity.example/custom/callback";
        assert_eq!(
            identity_admin_input(origin, callback)
                .unwrap()
                .redirect_uris,
            [callback]
        );
        for callback in [
            "",
            "javascript:alert(1)",
            "https://identity.example/cb#fragment",
            "https://user:pass@identity.example/cb",
            "http://remote.example/cb",
        ] {
            assert!(identity_admin_input(origin, callback).is_err());
        }
    }
}
