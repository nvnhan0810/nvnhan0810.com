use identity::seeder::handlers::identity_admin_seeder::run as create_identity_admin;
use identity::{application::ports::IdentityRepository, infrastructure::postgres::IdentityStore};
use sqlx::postgres::PgPoolOptions;

#[tokio::test]
#[ignore = "requires IDENTITY_TEST_DATABASE_URL pointing to a disposable PostgreSQL database"]
async fn identity_master_client_is_created_once_without_overwriting_existing_data() {
    let url = std::env::var("IDENTITY_TEST_DATABASE_URL")
        .expect("Set IDENTITY_TEST_DATABASE_URL to a fresh test DB");
    let pool = PgPoolOptions::new()
        .max_connections(4)
        .connect(&url)
        .await
        .unwrap();
    sqlx::migrate!("../../modules/identity/migrations")
        .run(&pool)
        .await
        .unwrap();
    let store = IdentityStore(pool.clone());
    let portal = "https://identity.nvnhan0810.com";
    let callback = "https://identity.nvnhan0810.com/custom/callback";
    let (a, b) = tokio::join!(
        create_identity_admin(&store, portal, callback),
        create_identity_admin(&store, portal, callback)
    );
    let a = a.unwrap();
    let b = b.unwrap();
    assert_eq!(usize::from(a.is_some()) + usize::from(b.is_some()), 1);
    assert_eq!(a.or(b).as_deref(), Some("identity"));
    let client = store.client("identity").await.unwrap();
    assert_eq!(client.client_type, "public");
    assert!(client.secret_hash.is_none());
    assert_eq!(client.redirect_uris, [callback]);
    assert_eq!(client.scopes, ["profile", "identity.clients.manage"]);
    // User-managed edits and disabled state must never be reset on deploy.
    sqlx::query("UPDATE sso_clients SET name='Manually edited',enabled=FALSE,version=version+1 WHERE client_id='identity'").execute(&pool).await.unwrap();
    let before = serde_json::to_value(store.client("identity").await.unwrap()).unwrap();
    assert!(
        create_identity_admin(
            &store,
            "https://another.example",
            "https://another.example/cb"
        )
        .await
        .unwrap()
        .is_none()
    );
    let after = serde_json::to_value(store.client("identity").await.unwrap()).unwrap();
    assert_eq!(before, after);
    let count: i64 =
        sqlx::query_scalar("SELECT count(*) FROM sso_clients WHERE client_id='identity'")
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(count, 1);

    // Exercise the real entry point and ordered runner against this disposable DB.
    let db = url::Url::parse(&url).unwrap();
    let run_command = || {
        std::process::Command::new(env!("CARGO_BIN_EXE_seed"))
            .current_dir(std::env::temp_dir())
            .env_clear()
            .env("DB_HOST", db.host_str().unwrap())
            .env("DB_PORT", db.port().unwrap_or(5432).to_string())
            .env("DB_NAME", db.path().trim_start_matches('/'))
            .env("DB_USERNAME", db.username())
            .env("DB_PASSWORD", db.password().unwrap_or(""))
            .env("IDENTITY_CORS_ORIGIN", portal)
            .env("IDENTITY_REDIRECT_URI", callback)
            .output()
            .unwrap()
    };
    let existing = run_command();
    assert!(existing.status.success());
    assert!(existing.stdout.is_empty());
    assert!(existing.stderr.is_empty());
    assert_eq!(
        before,
        serde_json::to_value(store.client("identity").await.unwrap()).unwrap()
    );

    sqlx::query("DELETE FROM sso_clients WHERE client_id='identity'")
        .execute(&pool)
        .await
        .unwrap();
    let first = run_command();
    assert!(first.status.success());
    assert!(first.stdout.is_empty());
    assert!(first.stderr.is_empty());
    assert_eq!(
        store.client("identity").await.unwrap().redirect_uris,
        [callback]
    );
    let second = run_command();
    assert!(second.status.success());
    assert!(second.stdout.is_empty());
    assert!(second.stderr.is_empty());
    pool.close().await;
}
