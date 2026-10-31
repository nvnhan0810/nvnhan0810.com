use identity::{
    application::{handlers::master_data_handler::create_client_if_missing, models::ClientInput},
    infrastructure::postgres::IdentityStore,
};

pub async fn insert(pool: &sqlx::PgPool) -> Result<(), Box<dyn std::error::Error>> {
    let input = ClientInput {
        client_id: "foreign-web".into(),
        name: "Foreign Local".into(),
        domain: Some("http://localhost:5174".into()),
        client_type: "public".into(),
        redirect_uris: vec!["http://localhost:5174/auth/callback".into()],
        scopes: vec!["profile".into()],
        enabled: true,
    };
    create_client_if_missing(&IdentityStore(pool.clone()), &input).await?;
    Ok(())
}
