use crate::application::error::{IdentityError, Result};
use sqlx::{migrate::Migrator, postgres::PgPoolOptions};

pub enum MigrationAction {
    Up,
    /// None reverts one applied migration; Some(0) reverts all.
    Down {
        target: Option<i64>,
    },
}

pub async fn migrate(
    database: sqlx::postgres::PgConnectOptions,
    action: MigrationAction,
) -> Result<()> {
    let pool = PgPoolOptions::new()
        .max_connections(1)
        .connect_with(database)
        .await?;
    apply(&pool, action).await?;
    pool.close().await;
    Ok(())
}

pub async fn apply(pool: &sqlx::PgPool, action: MigrationAction) -> Result<()> {
    let migrator: Migrator = sqlx::migrate!("./migrations");
    let result = match action {
        MigrationAction::Up => migrator.run(pool).await,
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
            if target != 0
                && (!versions.contains(&target)
                    || !migrator
                        .iter()
                        .any(|m| m.version == target && m.migration_type.is_up_migration()))
            {
                return Err(IdentityError::bad("invalid_migration_target"));
            }
            migrator.undo(pool, target).await
        }
    };
    result.map_err(|error| {
        eprintln!("Identity migration failed: {error}");
        IdentityError::internal()
    })
}
