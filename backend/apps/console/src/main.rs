const USAGE: &str = "Usage: console <seed | master-data | foreign:import [--dry-run|--apply]>\nReads DB_* from environment/.env; run migrations first.";

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args: Vec<_> = std::env::args().skip(1).collect();
    match args.as_slice() {
        [] => println!("{USAGE}"),
        [command] if command == "--help" || command == "-h" => println!("{USAGE}"),
        [command] if command == "seed" => console::commands::data::run(false).await?,
        [command] if command == "master-data" => console::commands::data::run(true).await?,
        [command, rest @ ..] if command == "foreign:import" => {
            console::commands::import_foreign::run(rest).await?
        }
        _ => return Err(USAGE.into()),
    }
    Ok(())
}
