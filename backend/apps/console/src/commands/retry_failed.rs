//! Requeue failed jobs without starting consumers or changing application payloads.
use app_config::{ConfigSource, DatabaseConfig};

pub async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let pool = sqlx::postgres::PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    let result = app_resources::queue::retry_failed(&pool).await;
    pool.close().await;
    println!("Requeued {} failed jobs.", result?);
    Ok(())
}
