#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args: Vec<_> = std::env::args().skip(1).collect();
    if args == ["--help"] || args == ["-h"] {
        println!("Usage: seed (local fixtures only; reads DB_* from environment/.env)");
        return Ok(());
    }
    if !args.is_empty() {
        return Err("Usage: seed".into());
    }
    console::commands::data::run(false).await
}
