use identity::MigrationAction;
use sqlx::{PgPool, migrate::Migrator};
pub async fn apply(
    pool: &PgPool,
    action: MigrationAction,
) -> Result<(), Box<dyn std::error::Error>> {
    let identity = sqlx::migrate!("../../modules/identity/migrations");
    let foreign = sqlx::migrate!("../../modules/foreign/migrations");
    let mut migrations: Vec<_> = identity.iter().chain(foreign.iter()).cloned().collect();
    migrations.sort_by_key(|m| (m.version, !m.migration_type.is_up_migration()));
    let migrator = Migrator {
        migrations: migrations.into(),
        ..Migrator::DEFAULT
    };
    match action {
        MigrationAction::Up => migrator.run(pool).await?,
        MigrationAction::Down { target } => {
            let exists: bool =
                sqlx::query_scalar("SELECT to_regclass('_sqlx_migrations') IS NOT NULL")
                    .fetch_one(pool)
                    .await?;
            if !exists {
                return Ok(());
            }
            let versions: Vec<i64> = sqlx::query_scalar(
                "SELECT version FROM _sqlx_migrations WHERE success ORDER BY version DESC",
            )
            .fetch_all(pool)
            .await?;
            let target = target.unwrap_or_else(|| versions.get(1).copied().unwrap_or(0));
            if target != 0 && !versions.contains(&target) {
                return Err("Invalid migration target".into());
            }
            migrator.undo(pool, target).await?;
        }
    }
    Ok(())
}
