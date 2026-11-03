const USAGE: &str = "Usage: console <seed | master-data | queue:work | wallets:auto-pay-due | wallets:process-reminders | wallets:process-periods | wallets:migrate-legacy --source <legacy-path> [--dry-run] [--replace]>\nReads DB_* from environment/.env; run migrations first.";

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args: Vec<_> = std::env::args().skip(1).collect();
    match args.as_slice() {
        [command, rest @ ..] if command == "wallets:migrate-legacy" => {
            console::commands::migrate_wallets_legacy::run(rest).await?
        }
        [] => println!("{USAGE}"),
        [command] if command == "--help" || command == "-h" => println!("{USAGE}"),
        [command] if command == "seed" => console::commands::data::run(false).await?,
        [command] if command == "master-data" => console::commands::data::run(true).await?,
        [command] if command == "queue:work" => console::commands::queue::run().await?,
        [command]
            if [
                "wallets:auto-pay-due",
                "wallets:process-reminders",
                "wallets:process-periods",
            ]
            .contains(&command.as_str()) =>
        {
            console::commands::wallets::run(command).await?
        }
        _ => return Err(USAGE.into()),
    }
    Ok(())
}
