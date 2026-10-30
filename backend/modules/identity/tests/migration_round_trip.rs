// Uses a unique schema inside the explicitly supplied test database.
use identity::infrastructure::migrations::{MigrationAction, apply};
use sqlx::postgres::PgPoolOptions;
use uuid::Uuid;

#[tokio::test]
#[ignore = "requires IDENTITY_TEST_DATABASE_URL pointing to a disposable PostgreSQL database"]
async fn initial_schema_supports_idempotent_up_and_complete_rollback() {
    let url = std::env::var("IDENTITY_TEST_DATABASE_URL").expect("Set IDENTITY_TEST_DATABASE_URL");
    let admin = PgPoolOptions::new()
        .max_connections(1)
        .connect(&url)
        .await
        .unwrap();
    let schema = format!("identity_migration_test_{}", Uuid::new_v4().simple());
    // Identifier contains only a fixed prefix and a generated UUID, never user input.
    sqlx::query(sqlx::AssertSqlSafe(format!("CREATE SCHEMA {schema}")))
        .execute(&admin)
        .await
        .unwrap();
    let search_path = schema.clone();
    let pool = PgPoolOptions::new()
        .max_connections(2)
        .after_connect(move |connection, _| {
            let schema = search_path.clone();
            Box::pin(async move {
                sqlx::query("SELECT set_config('search_path',$1,false)")
                    .bind(schema)
                    .execute(connection)
                    .await?;
                Ok(())
            })
        })
        .connect(&url)
        .await
        .unwrap();

    // Down on an untouched schema is a no-op.
    apply(&pool, MigrationAction::Down { target: None })
        .await
        .unwrap();
    apply(&pool, MigrationAction::Up).await.unwrap();
    apply(&pool, MigrationAction::Up).await.unwrap();
    let count: i64 = sqlx::query_scalar("SELECT count(*) FROM _sqlx_migrations")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(count, 1);
    let removed: bool=sqlx::query_scalar("SELECT NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='sso_clients' AND column_name='audiences')").fetch_one(&pool).await.unwrap();
    assert!(removed);

    apply(&pool, MigrationAction::Down { target: None })
        .await
        .unwrap();
    let count: i64 = sqlx::query_scalar("SELECT count(*) FROM _sqlx_migrations")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(count, 0);
    let table: bool = sqlx::query_scalar("SELECT to_regclass('identity_users') IS NOT NULL")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert!(!table);
    assert!(
        apply(&pool, MigrationAction::Down { target: Some(123) })
            .await
            .is_err()
    );
    apply(&pool, MigrationAction::Up).await.unwrap();
    apply(
        &pool,
        MigrationAction::Down {
            target: Some(20261030000001),
        },
    )
    .await
    .unwrap();
    apply(&pool, MigrationAction::Down { target: Some(0) })
        .await
        .unwrap();
    let table: bool = sqlx::query_scalar("SELECT to_regclass('identity_users') IS NOT NULL")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert!(!table);
    apply(&pool, MigrationAction::Up).await.unwrap();
    apply(&pool, MigrationAction::Down { target: Some(0) })
        .await
        .unwrap();
    sqlx::query("DROP TABLE _sqlx_migrations")
        .execute(&pool)
        .await
        .unwrap();
    pool.close().await;
    sqlx::query(sqlx::AssertSqlSafe(format!("DROP SCHEMA {schema}")))
        .execute(&admin)
        .await
        .unwrap();
    admin.close().await;
}
