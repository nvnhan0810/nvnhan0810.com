pub mod data;
pub mod handlers;

use crate::{application::error::Result, infrastructure::postgres::IdentityStore};

/// Seed Identity in dependency order, using a pool supplied by the console.
pub async fn run(pool: &sqlx::PgPool, portal_url: &str, redirect_uri: &str) -> Result<()> {
    let store = IdentityStore(pool.clone());
    // 1. Clients precede any future client-dependent Identity master data.
    handlers::identity_admin_seeder::run(&store, portal_url, redirect_uri).await?;
    // Await dependent seeders below their prerequisites; stop on the first error.
    Ok(())
}
