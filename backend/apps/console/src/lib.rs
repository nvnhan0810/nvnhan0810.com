pub mod commands;
mod identity_master_data_config;
pub use identity_master_data_config::IdentityMasterDataConfig;

/// Production reference data; never includes local fixtures.
pub async fn master_data_all(
    pool: &sqlx::PgPool,
    config: &IdentityMasterDataConfig,
) -> Result<(), Box<dyn std::error::Error>> {
    identity::master_data::run(pool, &config.portal_url, &config.redirect_uri).await?;
    Ok(())
}

/// Local fixtures only. No users or Google identities are fabricated.
pub async fn seed_all(pool: &sqlx::PgPool) -> Result<(), Box<dyn std::error::Error>> {
    identity::master_data::run(
        pool,
        "http://localhost:5174",
        "http://localhost:5174/auth/callback",
    )
    .await?;
    commands::create_foreign_local_client::insert(pool).await?;
    Ok(())
}

pub mod migrations;
