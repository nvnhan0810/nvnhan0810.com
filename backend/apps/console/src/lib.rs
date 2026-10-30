mod identity_seed_config;
pub use identity_seed_config::IdentitySeedConfig;

/// Cross-module dependency order. Each module owns its internal seed ordering.
pub async fn seed_all(
    pool: &sqlx::PgPool,
    identity: &IdentitySeedConfig,
) -> Result<(), Box<dyn std::error::Error>> {
    identity::seeder::run(pool, &identity.portal_url, &identity.redirect_uri).await?;
    // Register modules depending on Identity below this call, sequentially.
    Ok(())
}
