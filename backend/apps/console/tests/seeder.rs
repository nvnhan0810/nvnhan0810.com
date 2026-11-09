use identity::master_data::handlers::identity_admin_seeder::run as create_identity_admin;
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
    assert!(!client.is_public);
    let root_email = "nguyenvannhan0810@gmail.com";
    let assignment_count = || async {
        sqlx::query_scalar::<_, i64>("SELECT count(*) FROM identity_user_apps a JOIN identity_users u ON u.id=a.user_id WHERE lower(u.email)=$1 AND a.sso_client_id=$2")
            .bind(root_email).bind(client.id).fetch_one(&pool).await.unwrap()
    };
    assert_eq!(assignment_count().await, 1);
    // Reconcile a previously public client, even after its client ID changed.
    sqlx::query("UPDATE sso_clients SET client_id=$1,is_public=TRUE WHERE id=$2")
        .bind(uuid::Uuid::new_v4().to_string())
        .bind(client.id)
        .execute(&pool)
        .await
        .unwrap();
    sqlx::query("DELETE FROM identity_user_apps WHERE sso_client_id=$1")
        .bind(client.id)
        .execute(&pool)
        .await
        .unwrap();
    assert!(
        create_identity_admin(&store, portal, callback)
            .await
            .unwrap()
            .is_none()
    );
    assert!(!store.client_by_id(client.id).await.unwrap().is_public);
    assert_eq!(assignment_count().await, 1);
    sqlx::query("UPDATE sso_clients SET client_id='identity' WHERE id=$1")
        .bind(client.id)
        .execute(&pool)
        .await
        .unwrap();
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
        std::process::Command::new(env!("CARGO_BIN_EXE_console"))
            .arg("master-data")
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
    // Local seed contains both clients and must never use production callbacks.
    sqlx::query("DELETE FROM sso_clients WHERE client_id IN ('identity', 'foreign-web')")
        .execute(&pool)
        .await
        .unwrap();
    console::seed_all(&pool).await.unwrap();
    console::seed_all(&pool).await.unwrap();
    for id in ["identity", "foreign-web"] {
        let client = store.client(id).await.unwrap();
        assert_eq!(client.domain.as_deref(), Some("http://localhost:5173"));
        assert_eq!(
            client.redirect_uris,
            ["http://localhost:5173/auth/callback"]
        );
        assert_eq!(client.client_type, "public");
    }
    assert_eq!(
        store.client("foreign-web").await.unwrap().scopes,
        ["profile"]
    );
    pool.close().await;
}
