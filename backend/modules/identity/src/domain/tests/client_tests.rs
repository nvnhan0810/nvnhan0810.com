use crate::domain::{
    entities::sso_client::SsoClient,
    errors::client_error::ClientError,
    value_objects::{ClientId, ClientName, ClientSecretHash, ClientType, RedirectUri, SsoClientId},
};

fn client() -> SsoClient {
    SsoClient::register(
        SsoClientId(1),
        ClientId::parse("wallets").unwrap(),
        ClientName::parse("Wallets").unwrap(),
        ClientType::Public,
        vec![RedirectUri::parse("https://wallets.example/callback").unwrap()],
        None,
    )
    .unwrap()
}
#[test]
fn invalid_identifiers_and_callbacks() {
    for id in ["", "-wallets", "Wallets", "wallets_web"] {
        assert!(ClientId::parse(id).is_err());
    }
    for uri in [
        "http://example.com/callback",
        "https://user:pass@example.com",
        "https://example.com/#fragment",
        "javascript:alert(1)",
        "https://*.example.com/cb",
    ] {
        assert!(RedirectUri::parse(uri).is_err(), "{uri}");
    }
    assert!(RedirectUri::parse("com.example.app:/callback").is_ok());
    assert!(RedirectUri::parse("http://127.0.0.1:8080/callback").is_ok());
}
#[test]
fn callback_matching_is_exact_and_disabled_clients_are_rejected() {
    let mut client = client();
    assert!(
        client
            .ensure_can_authorize("https://wallets.example/callback")
            .is_ok()
    );
    assert_eq!(
        client.ensure_can_authorize("https://wallets.example/callback/"),
        Err(ClientError::RedirectUriNotAllowed)
    );
    client.set_enabled(false).unwrap();
    assert_eq!(
        client.ensure_can_authorize("https://wallets.example/callback"),
        Err(ClientError::DisabledClient)
    );
}
#[test]
fn invalid_mutation_preserves_state() {
    let mut client = client();
    let version = client.version();
    assert!(
        client
            .update_details(ClientName::parse("Changed").unwrap(), vec![])
            .is_err()
    );
    assert_eq!(client.version(), version);
    assert_eq!(client.name().as_str(), "Wallets");
    assert!(
        client
            .rotate_secret(ClientSecretHash::from_encoded("fixture".into()).unwrap())
            .is_err()
    );
}
#[test]
fn confidential_requires_secret_and_public_rejects_one() {
    let make = |kind, secret| {
        SsoClient::register(
            SsoClientId(2),
            ClientId::parse("test").unwrap(),
            ClientName::parse("Test").unwrap(),
            kind,
            vec![RedirectUri::parse("https://example.com/cb").unwrap()],
            secret,
        )
    };
    assert!(make(ClientType::Confidential, None).is_err());
    assert!(
        make(
            ClientType::Public,
            Some(ClientSecretHash::from_encoded("hash".into()).unwrap())
        )
        .is_err()
    );
    assert!(
        make(
            ClientType::Confidential,
            Some(ClientSecretHash::from_encoded("hash".into()).unwrap())
        )
        .is_ok()
    );
}
