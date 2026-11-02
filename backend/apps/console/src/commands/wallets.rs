use app_config::{ConfigSource, DatabaseConfig};
use sqlx::postgres::PgPoolOptions;

pub async fn run(command: &str) -> Result<(), Box<dyn std::error::Error>> {
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let pool = PgPoolOptions::new()
        .max_connections(2)
        .connect_with(database.connect_options())
        .await?;
    let result: Result<(), Box<dyn std::error::Error + Send + Sync>> = async {
        wallets::infrastructure::process(&pool, command == "wallets:auto-pay-due").await?;
        if command == "wallets:process-reminders" {
            if let Some(token) = source
                .optional("TELEGRAM_BOT_TOKEN")
                .filter(|v| !v.is_empty())
            {
                let origin = source
                    .optional("WALLETS_WEB_ORIGIN")
                    .unwrap_or("https://wallets.nvnhan0810.com");
                let sent =
                    wallets::infrastructure::notifications::remind(&pool, token, origin).await?;
                println!("Sent {sent} Wallets reminders");
            } else {
                println!("TELEGRAM_BOT_TOKEN is unset; reminder states updated, delivery skipped");
            }
        }
        Ok(())
    }
    .await;
    pool.close().await;
    result.map_err(|e| e as Box<dyn std::error::Error>)
}
