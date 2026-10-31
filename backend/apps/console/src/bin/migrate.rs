use app_config::{ConfigSource, DatabaseConfig};
use identity::MigrationAction;

fn action(args: &[String]) -> Result<Option<MigrationAction>, String> {
    match args {
        [] => Ok(Some(MigrationAction::Up)),
        [command] if command == "up" => Ok(Some(MigrationAction::Up)),
        [command] if command == "down" => Ok(Some(MigrationAction::Down { target: None })),
        [command, target] if command == "down" => {
            let target = target
                .parse::<i64>()
                .ok()
                .filter(|v| *v >= 0)
                .ok_or("Target must be a non-negative migration version or 0")?;
            Ok(Some(MigrationAction::Down {
                target: Some(target),
            }))
        }
        [command] if command == "--help" || command == "-h" => Ok(None),
        _ => Err("Usage: migrate [up | down [VERSION | 0]]".into()),
    }
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let Some(action) = action(&std::env::args().skip(1).collect::<Vec<_>>())? else {
        println!("Usage: migrate [up | down [VERSION | 0]]");
        return Ok(());
    };
    let source = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&source)?;
    let pool = sqlx::postgres::PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    console::migrations::apply(&pool, action).await?;
    pool.close().await;
    println!("Workspace migration command completed.");
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;
    fn args(values: &[&str]) -> Vec<String> {
        values.iter().map(|s| (*s).into()).collect()
    }
    #[test]
    fn up_and_down_commands_are_explicit() {
        assert!(matches!(action(&[]).unwrap(), Some(MigrationAction::Up)));
        assert!(matches!(
            action(&args(&["down"])).unwrap(),
            Some(MigrationAction::Down { target: None })
        ));
        assert!(matches!(
            action(&args(&["down", "0"])).unwrap(),
            Some(MigrationAction::Down { target: Some(0) })
        ));
        assert!(matches!(
            action(&args(&["down", "20261030000001"])).unwrap(),
            Some(MigrationAction::Down {
                target: Some(20261030000001)
            })
        ));
        for values in [
            vec!["down", "-1"],
            vec!["down", "abc"],
            vec!["reset"],
            vec!["up", "0"],
        ] {
            assert!(action(&args(&values)).is_err());
        }
        assert!(action(&args(&["--help"])).unwrap().is_none());
    }
}
