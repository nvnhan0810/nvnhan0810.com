use identity::{application::ports::CorsOriginRepository, infrastructure::postgres::IdentityStore};
use sqlx::postgres::PgPoolOptions;
use uuid::Uuid;

#[tokio::test]
#[ignore = "requires IDENTITY_TEST_DATABASE_URL pointing to a disposable PostgreSQL database"]
async fn cors_uses_current_enabled_client_domains_only() {
    let url = std::env::var("IDENTITY_TEST_DATABASE_URL").expect("Set a disposable test DB");
    let pool = PgPoolOptions::new()
        .max_connections(2)
        .connect(&url)
        .await
        .unwrap();
    sqlx::migrate!("./migrations").run(&pool).await.unwrap();
    let store = IdentityStore(pool.clone());
    let id = Uuid::new_v4();
    sqlx::query("INSERT INTO sso_clients(id,client_id,name,client_type,domain,redirect_uris,scopes) VALUES($1,'cors-test','CORS test','public','https://wallets.example',ARRAY['https://callback.example/auth/callback'],ARRAY['profile'])")
        .bind(id).execute(&pool).await.unwrap();
    assert!(
        store
            .allows_origin("https://wallets.example")
            .await
            .unwrap()
    );
    for origin in [
        "https://callback.example",
        "https://wallets.example.evil",
        "https://wallets.example/",
        "http://wallets.example",
        "null",
    ] {
        assert!(!store.allows_origin(origin).await.unwrap());
    }
    sqlx::query("UPDATE sso_clients SET enabled=false WHERE id=$1")
        .bind(id)
        .execute(&pool)
        .await
        .unwrap();
    assert!(
        !store
            .allows_origin("https://wallets.example")
            .await
            .unwrap()
    );
    sqlx::query("UPDATE sso_clients SET enabled=true,domain='https://todo.example' WHERE id=$1")
        .bind(id)
        .execute(&pool)
        .await
        .unwrap();
    assert!(
        !store
            .allows_origin("https://wallets.example")
            .await
            .unwrap()
    );
    assert!(store.allows_origin("https://todo.example").await.unwrap());
    sqlx::query("UPDATE sso_clients SET domain=NULL WHERE id=$1")
        .bind(id)
        .execute(&pool)
        .await
        .unwrap();
    assert!(!store.allows_origin("https://todo.example").await.unwrap());
    pool.close().await;
}
