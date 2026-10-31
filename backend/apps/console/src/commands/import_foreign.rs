use app_config::{ConfigSource, DatabaseConfig};
use sqlx::postgres::PgPoolOptions;
pub async fn run(args: &[String]) -> Result<(), Box<dyn std::error::Error>> {
    let (dry, run_id) = match args {
        [] => (true, None),
        [mode] if mode == "--dry-run" => (true, None),
        [mode] if mode == "--apply" => (false, None),
        [mode, flag, id] if (mode == "--apply" || mode == "--dry-run") && flag == "--run-id" => {
            (mode == "--dry-run", Some(id.parse::<uuid::Uuid>()?))
        }
        _ => {
            return Err("Usage: console foreign:import [--dry-run|--apply] [--run-id UUID]".into());
        }
    };
    let config = ConfigSource::load()?;
    let pool = PgPoolOptions::new()
        .max_connections(3)
        .connect_with(DatabaseConfig::from_source(&config)?.connect_options())
        .await?;
    let id = match run_id {
        Some(id) => id,
        None => foreign::infrastructure::importer::create_run(&pool, dry).await?,
    };
    println!("Foreign import run: {id} (dry_run={dry})");
    let result = foreign::infrastructure::importer::run(&pool, id, dry, &config).await;
    pool.close().await;
    result.map_err(|e| e.to_string().into())
}
