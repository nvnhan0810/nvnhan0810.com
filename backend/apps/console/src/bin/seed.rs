use app_config::{ConfigSource, DatabaseConfig};
use console::{IdentitySeedConfig, seed_all};
use sqlx::postgres::PgPoolOptions;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args: Vec<_> = std::env::args().skip(1).collect();
    if args == ["--help"] || args == ["-h"] {
        println!("Usage: seed (DB_* required; optional IDENTITY_CORS_ORIGIN, IDENTITY_REDIRECT_URI)");
        return Ok(());
    }
    if !args.is_empty() {
        return Err("Usage: seed".into());
    }
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let identity = IdentitySeedConfig::from_source(&source);
    let pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    let result = seed_all(&pool, &identity).await;
    pool.close().await;
    result
}
