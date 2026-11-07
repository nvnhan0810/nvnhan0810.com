use identity::{
    application::{handlers::master_data_handler::create_client_if_missing, models::ClientInput},
    infrastructure::postgres::IdentityStore,
};

pub async fn insert(pool: &sqlx::PgPool) -> Result<(), Box<dyn std::error::Error>> {
    let input = ClientInput {
        client_id: "todos-web".into(),
        name: "Todos Local".into(),
        domain: Some("http://localhost:5177".into()),
        client_type: "public".into(),
        redirect_uris: vec!["http://localhost:5177/auth/callback".into()],
        scopes: vec!["profile".into()],
        enabled: true,
        is_public: true,
    };
    create_client_if_missing(&IdentityStore(pool.clone()), &input).await?;
    Ok(())
}
