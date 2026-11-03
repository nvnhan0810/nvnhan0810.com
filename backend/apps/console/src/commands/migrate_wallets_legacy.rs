use app_config::{ConfigSource, DatabaseConfig};
use serde_json::Value;
use sqlx::postgres::PgPoolOptions;
use uuid::Uuid;

const EMAIL: &str = "nguyenvannhan0810@gmail.com";
const USAGE: &str =
    "console wallets:migrate-legacy --source /path/to/legacy-laravel [--dry-run] [--replace]";

pub async fn run(args: &[String]) -> Result<(), Box<dyn std::error::Error>> {
    let mut source = None;
    let mut dry_run = false;
    let mut replace = false;
    let mut args = args.iter();
    while let Some(arg) = args.next() {
        match arg.as_str() {
            "--source" => source = Some(args.next().ok_or(USAGE)?.clone()),
            "--dry-run" => dry_run = true,
            "--replace" => replace = true,
            "--help" | "-h" => {
                println!("{USAGE}");
                return Ok(());
            }
            _ => return Err(USAGE.into()),
        }
    }
    let source = std::fs::canonicalize(source.ok_or(USAGE)?)?;
    let output = tokio::task::spawn_blocking(move || {
        let mut command = std::process::Command::new("php");
        command.args([
            "-r",
            include_str!("wallets_legacy_export.php").trim_start_matches("<?php"),
        ]);
        command.arg(source);
        // The destination DB environment must not override the legacy .env.
        for (key, _) in std::env::vars_os() {
            let name = key.to_string_lossy();
            if name.starts_with("DB_")
                || [
                    "DATABASE_URL",
                    "APP_ENV",
                    "APP_CONFIG_CACHE",
                    "APP_BASE_PATH",
                ]
                .contains(&name.as_ref())
            {
                command.env_remove(key);
            }
        }
        command.output()
    })
    .await??;
    if !output.status.success() {
        return Err("Legacy export failed; verify the source path, PHP dependencies and legacy DB configuration. No destination changes were made.".into());
    }
    let snapshot: Value = serde_json::from_slice(&output.stdout)?;
    let account = wallets::domain::legacy::convert(&snapshot)?;
    println!("Destination owner: {EMAIL}");
    println!(
        "Wallets: {}, templates: {}, loans: {}, periods: {}, loan payments: {}, recurring items: {}, occurrences: {}, holidays: {}, settings: {}; transactions: 0",
        account.wallets.len(),
        account.templates.len(),
        account.loans.len(),
        account
            .loans
            .iter()
            .map(|l| l["schedule"].as_array().map_or(0, Vec::len))
            .sum::<usize>(),
        account
            .loans
            .iter()
            .map(|l| l["payments"].as_array().map_or(0, Vec::len))
            .sum::<usize>(),
        account.recurring.len(),
        account.reminders.len(),
        account.holidays.len(),
        account.settings.as_object().map_or(0, |v| v.len())
    );
    if dry_run {
        println!("Dry run: no destination connection or writes.");
        return Ok(());
    }
    let config = ConfigSource::load()?;
    let database = DatabaseConfig::from_source(&config)?;
    let pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database.connect_options())
        .await?;
    let mut tx = pool.begin().await?;
    // Only resolve the destination UUID; no legacy-user lookup or ownership filtering.
    let owner: Uuid = sqlx::query_scalar("SELECT id FROM identity_users WHERE lower(email)=$1")
        .bind(EMAIL)
        .fetch_one(&mut *tx)
        .await?;
    let current = wallets::infrastructure::load(&mut tx, owner).await?;
    let mut account = account;
    account.original_holidays = current.holidays.clone();
    // Shared holidays are merged by date and never removed by a personal import.
    for holiday in &current.holidays {
        if !account
            .holidays
            .iter()
            .any(|h| h["date"] == holiday["date"])
        {
            account.holidays.push(holiday.clone());
        }
    }
    let has_data = !current.wallets.is_empty()
        || !current.transactions.is_empty()
        || !current.loans.is_empty()
        || !current.templates.is_empty()
        || !current.recurring.is_empty()
        || !current.reminders.is_empty()
        || current.goal.is_object();
    if has_data && !replace {
        return Err("Destination already contains finance data. Use --replace to replace this user's finance data (including destination transactions). Shared holidays and other users are preserved.".into());
    }
    wallets::infrastructure::save(&mut tx, owner, &account).await?;
    tx.commit().await?;
    println!("Imported successfully into relational finance tables.");
    pool.close().await;
    Ok(())
}
