use axum::{
    Router,
    body::{Body, to_bytes},
    http::Request,
};
use serde_json::{Value, json};
use sqlx::PgPool;
use std::sync::Arc;
use tower::ServiceExt;
use uuid::Uuid;
async fn latest_task(pool: &PgPool) -> foreign::infrastructure::transcript_queue::TranscriptTask {
    let (media_id, payload): (i64, Value) = sqlx::query_as("SELECT id,payload FROM foreign_media WHERE payload->>'transcript_task_id' IS NOT NULL ORDER BY id DESC LIMIT 1").fetch_one(pool).await.unwrap();
    foreign::infrastructure::transcript_queue::TranscriptTask {
        id: payload["transcript_task_id"]
            .as_str()
            .unwrap()
            .parse()
            .unwrap(),
        media_id,
        video_id: payload["source_id"].as_str().unwrap().into(),
    }
}
async fn request(
    app: &Router,
    token: Option<&str>,
    method: &str,
    path: &str,
    body: Value,
) -> (u16, Value) {
    let mut builder = Request::builder()
        .uri(path)
        .method(method)
        .header("Content-Type", "application/json");
    if let Some(token) = token {
        builder = builder.header("Authorization", format!("Bearer {token}"));
    }
    let response = app
        .clone()
        .oneshot(builder.body(Body::from(body.to_string())).unwrap())
        .await
        .unwrap();
    let status = response.status().as_u16();
    let bytes = to_bytes(response.into_body(), 4 * 1024 * 1024)
        .await
        .unwrap();
    (
        status,
        serde_json::from_slice(&bytes).unwrap_or(Value::Null),
    )
}
async fn action(
    app: &Router,
    token: &str,
    path: &str,
    method: &str,
    data: Value,
    round: Value,
) -> (u16, Value) {
    request(
        app,
        Some(token),
        "POST",
        "/actions",
        json!({"path":path,"method":method,"data":data,"round_id":round}),
    )
    .await
}
async fn page(app: &Router, token: &str, path: &str) -> (u16, Value) {
    let mut url = url::Url::parse("http://local/pages").unwrap();
    url.query_pairs_mut().append_pair("path", path);
    request(
        app,
        Some(token),
        "GET",
        &format!("/pages?{}", url.query().unwrap()),
        Value::Null,
    )
    .await
}
async fn setup(pool: &PgPool) -> (Router, Uuid, String, Uuid, String) {
    use identity::{
        IdentityState,
        application::{config::IdentityConfig, models::AccessClaims},
        infrastructure::{crypto::JwtCrypto, google::GoogleProvider, postgres::IdentityStore},
    };
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
    sqlx::query("INSERT INTO sso_clients(id,client_id,name,client_type,redirect_uris,scopes) VALUES($1,'foreign-fixture','Fixture','public',ARRAY['http://localhost/auth/callback'],ARRAY['profile'])").bind(client).execute(pool).await.unwrap();
    let mut users = Vec::new();
    for _ in 0..2 {
        let id = Uuid::new_v4();
        sqlx::query(
            "INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Fixture')",
        )
        .bind(id)
        .bind(id.to_string())
        .bind(format!("{id}@example.test"))
        .execute(pool)
        .await
        .unwrap();
        let grant = Uuid::new_v4();
        sqlx::query("INSERT INTO identity_grants(id,user_id,sso_client_id,scopes,expires_at) VALUES($1,$2,$3,ARRAY['profile'],now()+interval '1 hour')").bind(grant).bind(id).bind(client).execute(pool).await.unwrap();
        let now = foreign::domain::games::now() as i64;
        let token = state
            .crypto
            .sign(&AccessClaims {
                iss: state.config.app_url.clone(),
                aud: state.config.app_url.clone(),
                sub: id,
                client_id: "foreign-fixture".into(),
                scope: "profile".into(),
                iat: now,
                exp: now + 900,
                jti: Uuid::new_v4(),
                grant_id: grant,
                is_system_root: false,
            })
            .unwrap();
        users.push((id, token));
    }
    let (other, other_token) = users.pop().unwrap();
    let (user, token) = users.pop().unwrap();
    (
        foreign::foreign_routes(state, pool.clone()).unwrap(),
        user,
        token,
        other,
        other_token,
    )
}
#[tokio::test]
#[ignore = "requires FOREIGN_TEST_TARGET (empty PostgreSQL)"]
async fn vocabulary_games_media_and_profile_preserve_ownership_and_remove_import() {
    let pool = PgPool::connect(&std::env::var("FOREIGN_TEST_TARGET").unwrap())
        .await
        .unwrap();
    // Simulate deployed schema and then upgrade without rewriting its checksum.
    let identity = sqlx::migrate!("../../modules/identity/migrations");
    let foreign = sqlx::migrate!("../../modules/foreign/migrations");
    let mut migrations: Vec<_> = identity
        .iter()
        .chain(foreign.iter())
        .filter(|m| m.version <= 20261101000001)
        .cloned()
        .collect();
    migrations.sort_by_key(|m| (m.version, !m.migration_type.is_up_migration()));
    sqlx::migrate::Migrator {
        migrations: migrations.into(),
        ..sqlx::migrate::Migrator::DEFAULT
    }
    .run(&pool)
    .await
    .unwrap();
    console::migrations::apply(&pool, identity::MigrationAction::Up)
        .await
        .unwrap();
    let removed:bool=sqlx::query_scalar("SELECT to_regclass('foreign_import_runs') IS NULL AND to_regclass('foreign_import_records') IS NULL").fetch_one(&pool).await.unwrap();
    assert!(removed);
    let (app, user, token, other, other_token) = setup(&pool).await;
    assert_eq!(
        request(&app, None, "GET", "/youtube/channels", Value::Null)
            .await
            .0,
        401
    );
    let channel_id = "UC1234567890123456789012";
    sqlx::query("INSERT INTO foreign_youtube_channels(user_id,channel_id,title,thumbnail_url,uploads_playlist_id) VALUES($1,$2,'English lessons','','UU1234567890123456789012')")
        .bind(user).bind(channel_id).execute(&pool).await.unwrap();
    let owned = request(&app, Some(&token), "GET", "/youtube/channels", Value::Null).await;
    assert_eq!(owned.0, 200);
    assert_eq!(owned.1["items"][0]["channel_id"], channel_id);
    assert_eq!(
        request(
            &app,
            Some(&other_token),
            "GET",
            "/youtube/channels",
            Value::Null
        )
        .await
        .1["items"],
        json!([])
    );
    assert_eq!(
        request(
            &app,
            Some(&other_token),
            "GET",
            &format!("/youtube/videos?channel_id={channel_id}"),
            Value::Null
        )
        .await
        .0,
        404
    );
    request(
        &app,
        Some(&other_token),
        "DELETE",
        &format!("/youtube/channels/{channel_id}"),
        Value::Null,
    )
    .await;
    assert_eq!(
        request(&app, Some(&token), "GET", "/youtube/channels", Value::Null)
            .await
            .1["items"]
            .as_array()
            .unwrap()
            .len(),
        1
    );
    request(
        &app,
        Some(&token),
        "DELETE",
        &format!("/youtube/channels/{channel_id}"),
        Value::Null,
    )
    .await;
    assert_eq!(
        request(&app, Some(&token), "GET", "/youtube/channels", Value::Null)
            .await
            .1["items"],
        json!([])
    );

    assert_eq!(
        request(&app, None, "GET", "/pages?path=/home/vocab", Value::Null)
            .await
            .0,
        401
    );
    assert_eq!(
        request(&app, None, "GET", "/imports", Value::Null).await.0,
        404
    );
    let mut ids = Vec::new();
    for word in ["apple", "banana", "orange", "pear"] {
        let result=action(&app,&token,"/home/lookup/save","POST",json!({"word":word,"phonetic":"/test/","meanings":[{"part_of_speech":"noun","definition":format!("Meaning of {word}"),"examples":["An example."],"synonyms":[],"antonyms":[]}]}),Value::Null).await;
        assert_eq!(result.0, 200, "{result:?}");
        ids.push(result.1["props"]["savedVocabularyId"].as_i64().unwrap());
    }
    let result = page(&app, &token, "/home/vocab").await;
    assert_eq!(result.0, 200);
    assert_eq!(result.1["items"].as_array().unwrap().len(), 4);
    assert_eq!(
        page(&app, &other_token, &format!("/home/vocab/{}", ids[0]))
            .await
            .0,
        404
    );
    assert_eq!(
        page(&app, &token, &format!("/home/vocab/{}", ids[0]))
            .await
            .1["vocab"]["meanings"][0]["examples"][0],
        "An example."
    );
    // Updating JSON affects only the owner's vocabulary, never another user's saved entry.
    let other_saved=action(&app,&other_token,"/home/lookup/save","POST",json!({"word":"apple","phonetic":"/test/","meanings":[{"part_of_speech":"noun","definition":"Meaning of apple","examples":["An example."],"synonyms":[],"antonyms":[]}]}),Value::Null).await;
    assert_eq!(other_saved.0, 200);
    let other_id = other_saved.1["props"]["savedVocabularyId"]
        .as_i64()
        .unwrap();
    let edit=action(&app,&token,"/home/lookup/save","POST",json!({"word":"apple","phonetic":"/test/","meanings":[{"part_of_speech":"noun","definition":"Personal edited definition","examples":["Personal example."],"synonyms":[],"antonyms":[]}]}),Value::Null).await;
    assert_eq!(edit.0, 200);
    assert_eq!(
        page(&app, &token, &format!("/home/vocab/{}", ids[0]))
            .await
            .1["vocab"]["meanings"][0]["definition"],
        "Personal edited definition"
    );
    assert_eq!(
        page(&app, &other_token, &format!("/home/vocab/{other_id}"))
            .await
            .1["vocab"]["meanings"][0]["definition"],
        "Meaning of apple"
    );
    let quiz = action(
        &app,
        &token,
        "/home/quiz/next",
        "POST",
        json!({}),
        Value::Null,
    )
    .await;
    assert_eq!(quiz.0, 200, "{quiz:?}");
    let round = quiz.1["props"]["roundId"].clone();
    assert_eq!(quiz.1["props"]["question"]["correct_answer"], "");
    assert!(quiz.1["props"]["reveal"].is_null());
    let private: Value = sqlx::query_scalar(
        "SELECT state FROM foreign_game_sessions WHERE user_id=$1 AND mode='quiz'",
    )
    .bind(user)
    .fetch_one(&pool)
    .await
    .unwrap();
    let wrong = private["question"]["options"]
        .as_array()
        .unwrap()
        .iter()
        .find(|choice| **choice != private["answer"])
        .unwrap()
        .clone();
    assert_eq!(
        action(
            &app,
            &token,
            "/home/quiz/rate",
            "POST",
            json!({"vocabulary_id":private["id"],"level":4}),
            round.clone()
        )
        .await
        .0,
        400
    );
    let answer = action(
        &app,
        &token,
        "/home/quiz/answer",
        "POST",
        json!({"choice":wrong,"correct_answer":wrong,"correct":true}),
        round.clone(),
    )
    .await;
    assert_eq!(answer.0, 200);
    assert_eq!(answer.1["props"]["wasCorrect"], false);
    assert_eq!(
        action(
            &app,
            &token,
            "/home/quiz/answer",
            "POST",
            json!({"choice":private["answer"]}),
            round.clone()
        )
        .await
        .0,
        200
    );
    let rated = action(
        &app,
        &token,
        "/home/quiz/rate",
        "POST",
        json!({"vocabulary_id":private["id"],"level":4}),
        round.clone(),
    )
    .await;
    assert_eq!(rated.0, 200, "{rated:?}");
    assert_eq!(rated.1["props"]["reveal"]["memory_level"], 4);
    assert_eq!(
        page(&app, &token, "/home/quiz/play").await.1["reveal"]["memory_level"],
        4
    );
    let memory: (i16, Value) = sqlx::query_as(
        "SELECT level,meaning_counts FROM foreign_vocabulary_memory WHERE vocabulary_id=$1",
    )
    .bind(private["id"].as_i64().unwrap())
    .fetch_one(&pool)
    .await
    .unwrap();
    assert_eq!(memory.0, 4);
    assert_eq!(
        memory.1[private["entry"]["_meaning_key"].as_str().unwrap()],
        1
    );
    let forbidden = action(
        &app,
        &other_token,
        "/home/quiz/rate",
        "POST",
        json!({"vocabulary_id":private["id"],"level":1}),
        Value::Null,
    )
    .await;
    assert_ne!(forbidden.0, 200);
    let count: i64 = sqlx::query_scalar("SELECT count(*) FROM foreign_attempts WHERE user_id=$1")
        .bind(user)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(count, 1);
    let next = action(
        &app,
        &token,
        "/home/quiz/next",
        "POST",
        json!({}),
        round.clone(),
    )
    .await;
    assert_eq!(next.0, 200);
    assert_eq!(
        action(
            &app,
            &token,
            "/home/quiz/answer",
            "POST",
            json!({"choice":private["answer"]}),
            round
        )
        .await
        .0,
        409
    );
    for mode in ["scramble", "wordle", "hangman", "word-search"] {
        let start = action(
            &app,
            &token,
            &format!("/home/puzzle/{mode}/next"),
            "POST",
            json!({}),
            Value::Null,
        )
        .await;
        assert_eq!(start.0, 200, "{mode}: {start:?}");
        let round = start.1["props"]["roundId"].clone();
        let private: Value = sqlx::query_scalar(
            "SELECT state FROM foreign_game_sessions WHERE user_id=$1 AND mode=$2",
        )
        .bind(user)
        .bind(mode)
        .fetch_one(&pool)
        .await
        .unwrap();
        let answers: Vec<(&str, Value)> = match mode {
            "scramble" => vec![("answer", json!({"answer":private["answer"]}))],
            "wordle" => vec![("guess", json!({"guess":private["answer"]}))],
            "hangman" => {
                let mut letters: Vec<_> = private["answer"].as_str().unwrap().chars().collect();
                letters.sort();
                letters.dedup();
                letters
                    .into_iter()
                    .map(|letter| ("guess", json!({"letter":letter.to_string()})))
                    .collect()
            }
            _ => private["paths"]
                .as_object()
                .unwrap()
                .values()
                .map(|path| ("find", json!({"cells":path["cells"]})))
                .collect(),
        };
        for (action_name, data) in answers {
            let response = action(
                &app,
                &token,
                &format!("/home/puzzle/{mode}/{action_name}"),
                "POST",
                data,
                round.clone(),
            )
            .await;
            assert_eq!(response.0, 200, "{mode}: {response:?}");
            if mode == "word-search" {
                for review in response.1["props"]["reviews"].as_array().unwrap() {
                    if review["reviewed"] == true {
                        continue;
                    }
                    let rated = action(
                        &app,
                        &token,
                        "/home/puzzle/word-search/rate",
                        "POST",
                        json!({"vocabulary_id":review["vocabulary_id"],"level":2}),
                        round.clone(),
                    )
                    .await;
                    assert_eq!(rated.0, 200, "{rated:?}");
                }
            }
        }
        let result = page(&app, &token, &format!("/home/puzzle/{mode}")).await;
        assert_eq!(result.1["wasCorrect"], true);
        assert_eq!(result.1["sessionCorrect"], 1);
        let reviews = if mode == "word-search" {
            assert!(result.1["reveal"].is_null());
            result.1["reviews"].as_array().unwrap().clone()
        } else {
            vec![result.1["reveal"].clone()]
        };
        for review in reviews {
            let rated = action(
                &app,
                &token,
                &format!("/home/puzzle/{mode}/rate"),
                "POST",
                json!({"vocabulary_id":review["vocabulary_id"],"level":2}),
                round.clone(),
            )
            .await;
            assert_eq!(rated.0, 200, "{mode}: {rated:?}");
        }
    }
    let result = page(&app, &token, "/home/profile").await;
    assert_eq!(result.0, 200);
    assert_eq!(result.1["stats"]["vocabulary_count"], 4);
    assert_eq!(result.1["history"].as_array().unwrap().len(), 5);
    assert_eq!(
        page(&app, &other_token, "/home/profile").await.1["history"],
        json!([])
    );
    let invalid = action(
        &app,
        &token,
        "/home/media/youtube",
        "POST",
        json!({"url":"https://evil.test/","title":"Invalid"}),
        Value::Null,
    )
    .await;
    assert_eq!(invalid.0, 400);
    let create = action(
        &app,
        &token,
        "/home/media/youtube",
        "POST",
        json!({"url":"https://youtu.be/dQw4w9WgXcQ","title":"Fixture","difficulty":"beginner"}),
        Value::Null,
    )
    .await;
    assert_eq!(create.0, 200, "{create:?}");
    let media_path = create.1["redirect"].as_str().unwrap();
    assert_eq!(page(&app, &other_token, media_path).await.0, 404);
    use foreign::infrastructure::transcript_queue as queue;
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript_status"],
        "queued"
    );
    // Saving a video dispatched a durable job; repeated dispatch cannot duplicate it.
    for _ in 0..2 {
        assert_eq!(
            action(
                &app,
                &token,
                &format!("{media_path}/captions"),
                "POST",
                json!({}),
                Value::Null
            )
            .await
            .0,
            200
        );
    }
    let job = latest_task(&pool).await;
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript_task_id"],
        job.id.to_string()
    );
    // Editing during an in-flight fetch cancels it and the old worker cannot overwrite the edit.
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/transcript"),
            "PUT",
            json!({"segments":[{"start":0,"end":1,"text":"Manual edit"}]}),
            Value::Null
        )
        .await
        .0,
        200
    );
    assert!(
        !queue::complete(
            &pool,
            &job,
            &[json!({"start":0,"end":1,"text":"Stale worker"})]
        )
        .await
        .unwrap()
    );
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript"],
        "Manual edit"
    );
    // Clear and request a fresh job. Retry failures at most three times, with backoff.
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/transcript"),
            "PUT",
            json!({"segments":[]}),
            Value::Null
        )
        .await
        .0,
        200
    );
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/captions"),
            "POST",
            json!({}),
            Value::Null
        )
        .await
        .0,
        200
    );
    for attempt in 1..=3 {
        assert!(
            queue::run_once_with(
                &pool,
                &latest_task(&pool).await,
                attempt as usize,
                |_| async { Err("Temporary provider failure".to_owned()) }
            )
            .await
            .is_err()
        );
        let status: String = sqlx::query_scalar(
            "SELECT payload->>'transcript_status' FROM foreign_media WHERE id=$1",
        )
        .bind(job.media_id)
        .fetch_one(&pool)
        .await
        .unwrap();
        assert_eq!(status, if attempt == 3 { "failed" } else { "queued" });
    }
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript_status"],
        "failed"
    );
    // An Apalis redelivery gets a new fencing token; late results are rejected.
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/captions"),
            "POST",
            json!({}),
            Value::Null
        )
        .await
        .0,
        200
    );
    let recovered = latest_task(&pool).await;
    let expired = job;
    assert!(
        !queue::complete(
            &pool,
            &expired,
            &[json!({"start":0,"end":1,"text":"Expired"})]
        )
        .await
        .unwrap()
    );
    assert!(
        queue::complete(
            &pool,
            &recovered,
            &[json!({"start":0,"end":2,"text":"Fetched caption"})]
        )
        .await
        .unwrap()
    );
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript_status"],
        "completed"
    );
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript"],
        "Fetched caption"
    );
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/captions"),
            "POST",
            json!({}),
            Value::Null
        )
        .await
        .0,
        400
    );
    let invalid = action(
        &app,
        &token,
        &format!("{media_path}/transcript"),
        "PUT",
        json!({"segments":[{"start":2,"end":1,"text":"Invalid"}]}),
        Value::Null,
    )
    .await;
    assert_eq!(invalid.0, 400);
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/transcript"),
            "PUT",
            json!({"segments":[{"start":0,"end":2,"text":"Hello."}]}),
            Value::Null
        )
        .await
        .0,
        200
    );
    assert_eq!(
        page(&app, &token, media_path).await.1["item"]["transcript_segments"][0]["text"],
        "Hello."
    );
    assert_eq!(
        action(
            &app,
            &token,
            &format!("{media_path}/listened"),
            "POST",
            json!({}),
            Value::Null
        )
        .await
        .0,
        200
    );
    assert_eq!(
        action(
            &app,
            &other_token,
            media_path,
            "DELETE",
            json!({}),
            Value::Null
        )
        .await
        .0,
        404
    );
    assert_eq!(
        action(&app, &token, media_path, "DELETE", json!({}), Value::Null)
            .await
            .0,
        200
    );
    let logs: i64 = sqlx::query_scalar("SELECT count(*) FROM foreign_listen_logs")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(logs, 0);
    let id = ids[0];
    assert_eq!(
        action(
            &app,
            &other_token,
            &format!("/home/vocab/{id}"),
            "DELETE",
            json!({}),
            Value::Null
        )
        .await
        .0,
        404
    );
    assert_eq!(
        action(
            &app,
            &token,
            &format!("/home/vocab/{id}"),
            "DELETE",
            json!({}),
            Value::Null
        )
        .await
        .0,
        200
    );
    assert_eq!(
        page(&app, &token, &format!("/home/vocab/{id}")).await.0,
        404
    );
    let attempts: i64 =
        sqlx::query_scalar("SELECT count(*) FROM foreign_attempts WHERE user_id=$1")
            .bind(other)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(attempts, 0);
    console::migrations::apply(&pool, identity::MigrationAction::Down { target: Some(0) })
        .await
        .unwrap();
    pool.close().await;
}
