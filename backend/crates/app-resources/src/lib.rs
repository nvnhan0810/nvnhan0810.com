//! Process-owned PostgreSQL connections shared by application modules.
use std::time::Duration;

pub struct AppResources {
    pub database: sqlx::PgPool,
}
impl AppResources {
    pub async fn connect(database: sqlx::postgres::PgConnectOptions) -> Result<Self, sqlx::Error> {
        let database = sqlx::postgres::PgPoolOptions::new()
            .max_connections(10)
            .acquire_timeout(Duration::from_secs(10))
            .connect_with(database)
            .await?;
        Ok(Self { database })
    }
    pub async fn shutdown(self) {
        self.database.close().await;
    }
}

/// Applied by the workspace migration command before API/queue startup.
pub async fn migrate_queue(pool: &sqlx::PgPool) -> Result<(), apalis_postgres::Error> {
    apalis_postgres::PostgresStorage::setup(pool).await
}
