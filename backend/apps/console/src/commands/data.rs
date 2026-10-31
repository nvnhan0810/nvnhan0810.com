use crate::{IdentityMasterDataConfig, master_data_all, seed_all};
use app_config::{ConfigSource, DatabaseConfig};
use sqlx::postgres::PgPoolOptions;

pub async fn run(master: bool) -> Result<(), Box<dyn std::error::Error>> {
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    let result = if master {
        master_data_all(&pool, &IdentityMasterDataConfig::from_source(&source)).await
    } else {
        seed_all(&pool).await
    };
    pool.close().await;
    result
}
