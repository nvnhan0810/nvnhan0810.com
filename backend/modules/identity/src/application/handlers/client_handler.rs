use crate::{
    IdentityState,
    application::{
        error::{IdentityError, Result},
        models::{Client, ClientInput, ClientUpdate},
    },
    domain::{
        entities::sso_client::SsoClient,
        value_objects::{
            ClientId, ClientName, ClientSecretHash, ClientType, RedirectUri, Scope, SsoClientId,
            WebOrigin,
        },
    },
};
use uuid::Uuid;

pub(crate) fn validate(id: Uuid, input: &ClientInput, secret: Option<&str>) -> Result<SsoClient> {
    let invalid = || IdentityError::bad("invalid_client_metadata");
    let kind = match input.client_type.as_str() {
        "public" => ClientType::Public,
        "confidential" => ClientType::Confidential,
        _ => return Err(invalid()),
    };
    if input.scopes.is_empty()
        || input.scopes.iter().any(|s| Scope::parse(s).is_err())
        || input
            .scopes
            .iter()
            .collect::<std::collections::HashSet<_>>()
            .len()
            != input.scopes.len()
        || input.scopes.len() > 100
        || input.redirect_uris.len() > 20
        || input
            .domain
            .as_ref()
            .is_some_and(|d| WebOrigin::parse(d).is_err())
    {
        return Err(invalid());
    }
    SsoClient::register(
        SsoClientId(id.as_u128()),
        ClientId::parse(&input.client_id).map_err(|_| invalid())?,
        ClientName::parse(&input.name).map_err(|_| invalid())?,
        kind,
        input
            .redirect_uris
            .iter()
            .map(|s| RedirectUri::parse(s).map_err(|_| invalid()))
            .collect::<Result<_>>()?,
        secret
            .map(|s| ClientSecretHash::from_encoded(s.to_owned()))
            .transpose()
            .map_err(|_| invalid())?,
    )
    .map_err(|_| invalid())
}

pub async fn register(
    state: &IdentityState,
    input: ClientInput,
) -> Result<(Client, Option<String>)> {
    let secret = (input.client_type == "confidential").then(|| state.crypto.random_token());
    let hash = secret.as_deref().map(|s| state.crypto.hash(s));
    let id = Uuid::new_v4();
    let aggregate = validate(id, &input, hash.as_deref())?;
    let mut input = input;
    input.name = aggregate.name().as_str().to_owned();
    let client = state
        .store
        .register_client(
            Uuid::from_u128(aggregate.id().0),
            &input,
            aggregate.secret_hash().map(|h| h.encoded()),
        )
        .await?;
    Ok((client, secret))
}

pub async fn update(state: &IdentityState, id: Uuid, update: ClientUpdate) -> Result<Client> {
    let existing = state.store.client_by_id(id).await?;
    let input = ClientInput {
        client_id: existing.client_id.clone(),
        name: update.name,
        domain: update.domain,
        client_type: existing.client_type.clone(),
        redirect_uris: update.redirect_uris,
        scopes: update.scopes,
        enabled: update.enabled,
    };
    let aggregate = validate(id, &input, existing.secret_hash.as_deref())?;
    let mut input = input;
    input.name = aggregate.name().as_str().to_owned();
    state.store.update_client(id, update.version, &input).await
}

pub async fn rotate(state: &IdentityState, id: Uuid, version: i64) -> Result<(Client, String)> {
    let secret = state.crypto.random_token();
    let client = state
        .store
        .rotate_secret(id, version, &state.crypto.hash(&secret))
        .await?;
    Ok((client, secret))
}

pub async fn list(state: &IdentityState, after: Option<Uuid>, limit: i64) -> Result<Vec<Client>> {
    state.store.list_clients(after, limit.clamp(1, 100)).await
}

pub async fn get(state: &IdentityState, id: Uuid) -> Result<Client> {
    state.store.client_by_id(id).await
}

#[cfg(test)]
mod tests {
    use super::*;
    fn input() -> ClientInput {
        ClientInput {
            client_id: "new-service".into(),
            name: "New service".into(),
            domain: None,
            client_type: "public".into(),
            redirect_uris: vec!["https://new.example/callback".into()],
            scopes: vec!["reports:read".into()],
            enabled: true,
        }
    }
    #[test]
    fn client_permissions_need_no_environment_catalog() {
        assert!(validate(Uuid::new_v4(), &input(), None).is_ok());
    }
    #[test]
    fn client_domain_is_an_exact_browser_origin() {
        let mut client = input();
        for domain in ["https://new.example", "http://localhost:5174"] {
            client.domain = Some(domain.into());
            assert!(validate(Uuid::new_v4(), &client, None).is_ok());
        }
        for domain in [
            "https://new.example/",
            "https://new.example/path",
            "https://new.example?x=1",
            "com.example.app:/callback",
            "null",
        ] {
            client.domain = Some(domain.into());
            assert!(validate(Uuid::new_v4(), &client, None).is_err());
        }
    }
    #[test]
    fn invalid_or_duplicate_permissions_are_rejected() {
        let mut client = input();
        client.scopes.push("reports:read".into());
        assert!(validate(Uuid::new_v4(), &client, None).is_err());
        client = input();
        client.scopes = vec!["read write".into()];
        assert!(validate(Uuid::new_v4(), &client, None).is_err());
    }
}
