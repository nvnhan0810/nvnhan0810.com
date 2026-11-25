//! Expire Todo ↔ Agent claims whose lease ran out (Phase 4). Schedule it every minute or so.
use app_config::{ConfigSource, DatabaseConfig};

pub async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let pool = sqlx::postgres::PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    let result = todos::application::agent_run::sweep_expired(&pool).await;
    pool.close().await;
    println!("Expired {} Agent leases.", result?);
    Ok(())
}
