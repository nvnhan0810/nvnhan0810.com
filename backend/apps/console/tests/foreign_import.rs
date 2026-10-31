use app_config::ConfigSource;
use foreign::{
    domain::dictionary::Entry,
    infrastructure::{importer, vocabulary},
};
use sqlx::postgres::PgPoolOptions;
use uuid::Uuid;

#[tokio::test]
#[ignore = "requires two empty disposable PostgreSQL databases via FOREIGN_TEST_TARGET and FOREIGN_TEST_SOURCE"]
async fn imports_laravel_words_without_duplicates_and_preserves_ownership() {
    let target = PgPoolOptions::new()
        .max_connections(5)
        .connect(&std::env::var("FOREIGN_TEST_TARGET").unwrap())
        .await
        .unwrap();
    let source = PgPoolOptions::new()
        .max_connections(2)
        .connect(&std::env::var("FOREIGN_TEST_SOURCE").unwrap())
        .await
        .unwrap();
    console::migrations::apply(&target, identity::MigrationAction::Up)
        .await
        .unwrap();
    sqlx::raw_sql(include_str!("fixtures/laravel_dictionary.sql"))
        .execute(&source)
        .await
        .unwrap();
    let user = Uuid::new_v4();
    let other = Uuid::new_v4();
    for id in [user, other] {
        sqlx::query(
            "INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Fixture')",
        )
        .bind(id)
        .bind(id.to_string())
        .bind(format!("{id}@example.test"))
        .execute(&target)
        .await
        .unwrap();
    }
    sqlx::raw_sql("INSERT INTO users(id,name,email,password) VALUES(1,'Legacy','legacy@example.test','unused'); INSERT INTO dictionary_entries(id,word,phonetic,audio_url,source,is_curated,save_count,created_at,updated_at) VALUES(1,'hello','ipa',NULL,'curated',true,1,'2025-01-01','2025-01-02'); INSERT INTO dictionary_meanings(id,dictionary_entry_id,part_of_speech,definition,created_at,updated_at) VALUES(1,1,'noun','A greeting.','2025-01-01','2025-01-02'); INSERT INTO dictionary_examples(dictionary_meaning_id,example,created_at,updated_at) VALUES(1,'Hello there.','2025-01-01','2025-01-02'); INSERT INTO vocabularies(id,user_id,dictionary_entry_id,times_quizzed,created_at,updated_at) VALUES(1,1,1,4,'2025-01-01','2025-01-02');").execute(&source).await.unwrap();
    let db_values = |name: &str, prefix: &str| {
        let url = url::Url::parse(&std::env::var(name).unwrap()).unwrap();
        let params: std::collections::HashMap<_, _> = url.query_pairs().into_owned().collect();
        [
            (
                "HOST",
                params
                    .get("host")
                    .cloned()
                    .unwrap_or_else(|| url.host_str().unwrap_or("localhost").into()),
            ),
            (
                "PORT",
                params
                    .get("port")
                    .cloned()
                    .unwrap_or_else(|| url.port().unwrap_or(5432).to_string()),
            ),
            ("NAME", url.path().trim_start_matches('/').into()),
            ("USERNAME", url.username().into()),
            ("PASSWORD", url.password().unwrap_or("").into()),
        ]
        .into_iter()
        .map(|(k, v)| (format!("{prefix}{k}"), v))
        .collect::<Vec<_>>()
    };
    let mut base = db_values("FOREIGN_TEST_TARGET", "DB_");
    base.extend(db_values(
        "FOREIGN_TEST_SOURCE",
        "FOREIGN_IMPORT_SOURCE_DB_",
    ));
    base.extend([
        ("FOREIGN_IMPORT_SOURCE_ID".into(), "laravel-fixture".into()),
        ("FOREIGN_IMPORT_SOURCE_TIMEZONE".into(), "UTC".into()),
    ]);
    let config = |mapping: String| {
        ConfigSource::from_values(
            base.iter()
                .map(|(k, v)| (k.to_string(), v.to_string()))
                .chain([("FOREIGN_IMPORT_USER_MAP".into(), mapping)]),
        )
    };
    let id = importer::create_run(&target, true).await.unwrap();
    assert!(importer::create_run(&target, true).await.is_err());
    assert!(
        importer::run(&target, id, true, &config("{}".into()))
            .await
            .is_err()
    );
    let config = config(format!("{{\"1\":\"{user}\"}}"));
    let id = importer::create_run(&target, true).await.unwrap();
    importer::run(&target, id, true, &config).await.unwrap();
    assert!(
        vocabulary::list(&target, user, "", i64::MAX)
            .await
            .unwrap()
            .is_empty()
    );
    let id = importer::create_run(&target, false).await.unwrap();
    importer::run(&target, id, false, &config).await.unwrap();
    let words = vocabulary::list(&target, user, "", i64::MAX).await.unwrap();
    assert_eq!(words.len(), 1);
    assert_eq!(words[0].word, "hello");
    assert_eq!(
        words[0].entry["meanings"][0]["definitions"][0]["examples"][0],
        "Hello there."
    );
    assert!(
        vocabulary::list(&target, other, "", i64::MAX)
            .await
            .unwrap()
            .is_empty()
    );
    let count: i32 = sqlx::query_scalar("SELECT times_quizzed FROM foreign_vocabularies")
        .fetch_one(&target)
        .await
        .unwrap();
    assert_eq!(count, 4);
    let id = importer::create_run(&target, false).await.unwrap();
    importer::run(&target, id, false, &config).await.unwrap();
    let skipped: i64 = sqlx::query_scalar("SELECT skipped FROM foreign_import_runs WHERE id=$1")
        .bind(id)
        .fetch_one(&target)
        .await
        .unwrap();
    assert_eq!(skipped, 1);
    let entry: Entry = serde_json::from_value(words[0].entry.clone()).unwrap();
    let a = vocabulary::save(&target, user, &entry).await.unwrap();
    let b = vocabulary::save(&target, user, &entry).await.unwrap();
    assert_eq!(a, b);
    assert_eq!(
        vocabulary::list(&target, user, "hello", i64::MAX)
            .await
            .unwrap()
            .len(),
        1
    );
    check_http_permissions(&target, user, other).await;
    // Exercise the same CLI process invoked by the HTTP trigger.
    let run = importer::create_run(&target, true).await.unwrap();
    let output = std::process::Command::new(env!("CARGO_BIN_EXE_console"))
        .args(["foreign:import", "--dry-run", "--run-id", &run.to_string()])
        .current_dir(std::env::temp_dir())
        .env_clear()
        .envs(base)
        .env("FOREIGN_IMPORT_USER_MAP", format!("{{\"1\":\"{user}\"}}"))
        .output()
        .unwrap();
    assert!(
        output.status.success(),
        "{}",
        String::from_utf8_lossy(&output.stderr)
    );
    let status: String = sqlx::query_scalar("SELECT status FROM foreign_import_runs WHERE id=$1")
        .bind(run)
        .fetch_one(&target)
        .await
        .unwrap();
    assert_eq!(status, "completed");
    sqlx::query("UPDATE dictionary_meanings SET definition='Changed after import' WHERE id=1")
        .execute(&source)
        .await
        .unwrap();
    let run = importer::create_run(&target, false).await.unwrap();
    assert!(importer::run(&target, run, false, &config).await.is_err());
    let detail: String = sqlx::query_scalar("SELECT error FROM foreign_import_runs WHERE id=$1")
        .bind(run)
        .fetch_one(&target)
        .await
        .unwrap();
    assert!(detail.contains("Source changed"));
    console::migrations::apply(&target, identity::MigrationAction::Down { target: Some(0) })
        .await
        .unwrap();
    source.close().await;
    target.close().await;
}

async fn check_http_permissions(pool: &sqlx::PgPool, user: Uuid, other: Uuid) {
    use axum::{body::Body, http::Request};
    use identity::{
        IdentityState,
        application::{config::IdentityConfig, models::AccessClaims},
        infrastructure::{crypto::JwtCrypto, google::GoogleProvider, postgres::IdentityStore},
    };
    use std::sync::Arc;
    use tower::ServiceExt;
    let config = IdentityConfig {
        app_url: "http://localhost:8080".into(),
        google_client_id: "fixture".into(),
        google_client_secret: "fixture".into(),
        key_id: "fixture".into(),
        access_ttl: 900,
        refresh_ttl: 2592000,
    };
    let crypto = JwtCrypto::new(
        &config,
        include_bytes!("../../../modules/identity/tests/fixtures/private.pem"),
        include_bytes!("../../../modules/identity/tests/fixtures/public.pem"),
    )
    .unwrap();
    let state = IdentityState {
        google: Arc::new(GoogleProvider::new(config.clone()).unwrap()),
        config: Arc::new(config),
        store: Arc::new(IdentityStore(pool.clone())),
        crypto: Arc::new(crypto),
    };
    let client = Uuid::new_v4();
    sqlx::query("INSERT INTO sso_clients(id,client_id,name,client_type,redirect_uris,scopes) VALUES($1,'fixture','Fixture','public',ARRAY['http://localhost/auth/callback'],ARRAY['profile'])").bind(client).execute(pool).await.unwrap();
    let now = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_secs() as i64;
    let mut tokens = Vec::new();
    for owner in [user, other] {
        let grant = Uuid::new_v4();
        sqlx::query("INSERT INTO identity_grants(id,user_id,sso_client_id,scopes,expires_at) VALUES($1,$2,$3,ARRAY['profile'],now()+interval '1 hour')").bind(grant).bind(owner).bind(client).execute(pool).await.unwrap();
        let claims = AccessClaims {
            iss: state.config.app_url.clone(),
            aud: state.config.app_url.clone(),
            sub: owner,
            client_id: "fixture".into(),
            scope: "profile".into(),
            iat: now,
            exp: now + 900,
            jti: Uuid::new_v4(),
            grant_id: grant,
            is_system_root: false,
        };
        tokens.push(state.crypto.sign(&claims).unwrap());
    }
    let router = foreign::foreign_routes(state, pool.clone());
    let unauthorized = router
        .clone()
        .oneshot(
            Request::builder()
                .uri("/vocabularies")
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(unauthorized.status(), 401);
    for (index, token) in tokens.iter().enumerate() {
        let response = router
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/vocabularies")
                    .header("Authorization", format!("Bearer {token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(response.status(), 200);
        let body = axum::body::to_bytes(response.into_body(), 1024 * 1024)
            .await
            .unwrap();
        let json: serde_json::Value = serde_json::from_slice(&body).unwrap();
        assert_eq!(
            json["items"].as_array().unwrap().len(),
            usize::from(index == 0)
        );
    }
    let denied = router
        .clone()
        .oneshot(
            Request::builder()
                .method("POST")
                .uri("/imports")
                .header("Authorization", format!("Bearer {}", tokens[0]))
                .header("Content-Type", "application/json")
                .body(Body::from("{}"))
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(denied.status(), 405);
    let denied = router
        .clone()
        .oneshot(
            Request::builder()
                .method("GET")
                .uri("/imports?command=arbitrary")
                .header("Authorization", format!("Bearer {}", tokens[0]))
                .header("Content-Type", "application/json")
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(denied.status(), 400);
    sqlx::query("UPDATE identity_users SET email='nguyenvannhan0810@gmail.com' WHERE id=$1")
        .bind(user)
        .execute(pool)
        .await
        .unwrap();
    let status = router
        .oneshot(
            Request::builder()
                .uri(format!("/imports/{}", Uuid::new_v4()))
                .header("Authorization", format!("Bearer {}", tokens[0]))
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(status.status(), 404);
}
