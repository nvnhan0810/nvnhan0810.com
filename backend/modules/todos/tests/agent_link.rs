//! Phase 2: Todo <-> Agent account link. Needs a disposable PostgreSQL (TODO_TEST_DATABASE_URL).
use serde_json::json;
use sqlx::{Executor, PgPool, postgres::PgPoolOptions};
use todos::application::agent_link::{self as link, AgentLinkConfig, CompleteInput};
use uuid::Uuid;

fn config() -> AgentLinkConfig {
    AgentLinkConfig::new("https://agent.example", "https://agent.example", [3; 32])
}
async fn database() -> PgPool {
    let url = std::env::var("TODO_TEST_DATABASE_URL").unwrap();
    let admin = PgPoolOptions::new()
        .max_connections(1)
        .connect(&url)
        .await
        .unwrap();
    let schema = format!("todo_test_{}", Uuid::new_v4().simple());
    admin
        .execute(sqlx::AssertSqlSafe(format!("CREATE SCHEMA {schema}")))
        .await
        .unwrap();
    let pool = PgPoolOptions::new()
        .max_connections(3)
        .after_connect(move |connection, _| {
            let search = schema.clone();
            Box::pin(async move {
                sqlx::query("SELECT set_config('search_path',$1,false)")
                    .bind(search)
                    .execute(connection)
                    .await?;
                Ok(())
            })
        })
        .connect(&url)
        .await
        .unwrap();
    for sql in [
        include_str!("../../identity/migrations/20261030000001_identity.up.sql"),
        include_str!("../migrations/20261103000002_todos.up.sql"),
        include_str!("../migrations/20261123000001_in_review_and_dependencies.up.sql"),
        include_str!("../migrations/20261123000002_agent_links.up.sql"),
        include_str!("../migrations/20261123000003_agent_targets.up.sql"),
        include_str!("../migrations/20261125000001_agent_runs.up.sql"),
        include_str!("../migrations/20261125000002_task_identifiers.up.sql"),
        include_str!("../migrations/20261125000003_project_base_branch.up.sql"),
        include_str!("../migrations/20261125000004_task_base_branch.up.sql"),
        include_str!("../migrations/20261126000001_agent_root_auto.up.sql"),
    ] {
        pool.execute(sqlx::raw_sql(sql)).await.unwrap();
    }
    pool
}
async fn user(pool: &PgPool, email: &str) -> Uuid {
    let id = Uuid::new_v4();
    sqlx::query("INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Test')")
        .bind(id)
        .bind(id.to_string())
        .bind(email)
        .execute(pool)
        .await
        .unwrap();
    id
}
fn input(request: &Value, identity: Uuid, email: &str) -> CompleteInput {
    CompleteInput {
        request_id: request.0,
        code: request.1.clone(),
        identity_user_id: identity,
        email: email.into(),
        agent_token: "agi_0123456789abcdefghij".into(),
    }
}
type Value = (Uuid, String);
/// `start` and parse `req`/`code` back out of the fragment URL, as the Agent web does.
async fn begin(pool: &PgPool, owner: Uuid) -> Value {
    let started = link::start(pool, &config(), owner).await.unwrap();
    let url = url::Url::parse(started["redirect_url"].as_str().unwrap()).unwrap();
    let fragment = url.fragment().unwrap().to_owned();
    assert!(fragment.starts_with("/integrations/todo/consent?"));
    let query: std::collections::HashMap<_, _> =
        url::form_urlencoded::parse(fragment.split_once('?').unwrap().1.as_bytes())
            .into_owned()
            .collect();
    (query["req"].parse().unwrap(), query["code"].clone())
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn link_requires_same_uuid_and_email_and_is_single_use() {
    let pool = database().await;
    let alice = user(&pool, "Alice@Example.com").await;
    let mallory = user(&pool, "mallory@example.com").await;

    // Another account (different UUID) cannot consume Alice's request, even with her e-mail.
    let request = begin(&pool, alice).await;
    let error = link::complete(
        &pool,
        &config(),
        input(&request, mallory, "alice@example.com"),
    )
    .await
    .unwrap_err();
    assert_eq!((error.status, error.code), (403, "account_mismatch"));
    // Same UUID, different e-mail.
    let error = link::complete(
        &pool,
        &config(),
        input(&request, alice, "other@example.com"),
    )
    .await
    .unwrap_err();
    assert_eq!(error.code, "account_mismatch");
    // Wrong code.
    let mut wrong = input(&request, alice, "alice@example.com");
    wrong.code = "nope".into();
    assert_eq!(
        link::complete(&pool, &config(), wrong)
            .await
            .unwrap_err()
            .code,
        "request_invalid"
    );
    // Nothing was linked by the failures.
    assert!(
        link::authenticate(&pool, Some("Bearer tdi_x"))
            .await
            .is_err()
    );
    assert_eq!(
        link::status(&pool, &config(), alice).await.unwrap()["linked"],
        false
    );

    // E-mail comparison ignores case; success returns a tdi_ token that authenticates.
    let token = link::complete(
        &pool,
        &config(),
        input(&request, alice, "ALICE@example.com"),
    )
    .await
    .unwrap();
    assert!(token.starts_with("tdi_"));
    let me = link::authenticate(&pool, Some(&format!("Bearer {token}")))
        .await
        .unwrap();
    assert_eq!(me.user_id, alice);
    // The request is single use.
    let error = link::complete(
        &pool,
        &config(),
        input(&request, alice, "alice@example.com"),
    )
    .await
    .unwrap_err();
    assert_eq!(error.code, "request_invalid");
    let status = link::status(&pool, &config(), alice).await.unwrap();
    assert_eq!(status["linked"], true);
    assert!(!status.to_string().contains("tdi_") && !status.to_string().contains("agi_"));
    // The agi_ token is stored sealed, never in clear.
    let sealed: String =
        sqlx::query_scalar("SELECT outbound_token_enc FROM todo_agent_links WHERE user_id=$1")
            .bind(alice)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert!(!sealed.contains("agi_"));
    assert_eq!(
        link::unseal(&config(), alice, &sealed).unwrap(),
        "agi_0123456789abcdefghij"
    );
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn expired_requests_and_revoked_or_disabled_links_are_rejected() {
    let pool = database().await;
    let alice = user(&pool, "alice@example.com").await;

    let request = begin(&pool, alice).await;
    sqlx::query("UPDATE todo_agent_link_requests SET expires_at=now()-interval '1 second'")
        .execute(&pool)
        .await
        .unwrap();
    assert_eq!(
        link::complete(
            &pool,
            &config(),
            input(&request, alice, "alice@example.com")
        )
        .await
        .unwrap_err()
        .code,
        "request_invalid"
    );

    // Revoke -> old token gets 401, agi_ is handed back once and wiped, relink issues a new token.
    let request = begin(&pool, alice).await;
    let first = link::complete(
        &pool,
        &config(),
        input(&request, alice, "alice@example.com"),
    )
    .await
    .unwrap();
    let header = format!("Bearer {first}");
    assert!(link::authenticate(&pool, Some(&header)).await.is_ok());
    let agi = link::revoke(&pool, &config(), alice).await.unwrap();
    assert_eq!(agi.as_deref(), Some("agi_0123456789abcdefghij"));
    assert_eq!(
        link::authenticate(&pool, Some(&header))
            .await
            .unwrap_err()
            .status,
        401
    );
    assert!(
        link::revoke(&pool, &config(), alice)
            .await
            .unwrap()
            .is_none()
    );
    let request = begin(&pool, alice).await;
    let second = link::complete(
        &pool,
        &config(),
        input(&request, alice, "alice@example.com"),
    )
    .await
    .unwrap();
    assert_ne!(first, second);
    assert_eq!(
        link::authenticate(&pool, Some(&header))
            .await
            .unwrap_err()
            .status,
        401
    );
    let header = format!("Bearer {second}");
    assert!(link::authenticate(&pool, Some(&header)).await.is_ok());

    // Disabled account or changed e-mail -> 401 even with a valid token.
    sqlx::query("UPDATE identity_users SET email='changed@example.com' WHERE id=$1")
        .bind(alice)
        .execute(&pool)
        .await
        .unwrap();
    assert_eq!(
        link::authenticate(&pool, Some(&header))
            .await
            .unwrap_err()
            .status,
        401
    );
    sqlx::query("UPDATE identity_users SET email='alice@example.com',enabled=false WHERE id=$1")
        .bind(alice)
        .execute(&pool)
        .await
        .unwrap();
    assert_eq!(
        link::authenticate(&pool, Some(&header))
            .await
            .unwrap_err()
            .status,
        401
    );
    // Malformed / missing headers.
    for bad in [None, Some("tdi_x"), Some("Bearer agi_x"), Some("Bearer ")] {
        assert_eq!(
            link::authenticate(&pool, bad).await.unwrap_err().status,
            401
        );
    }
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn only_five_open_requests_are_kept_per_user() {
    let pool = database().await;
    let alice = user(&pool, "alice@example.com").await;
    for _ in 0..8 {
        begin(&pool, alice).await;
    }
    let count: i64 =
        sqlx::query_scalar("SELECT count(*) FROM todo_agent_link_requests WHERE user_id=$1")
            .bind(alice)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(count, 5);
}

/// Phase 3 invariants that live in the database layer: changing a project's Agent workspace
/// unpicks its tasks' repos, and auto can never be on without a repo and a project.
#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn agent_workspace_change_unpicks_task_repos() {
    use todos::application::{self as app, Error};
    let pool = database().await;
    let owner = user(&pool, "targets@example.com").await;
    app::action(
        &pool,
        owner,
        "/todos/projects",
        "POST",
        &json!({"name":"P","agent_workspace_id":"ws-1","agent_workspace_name":"Work"}),
    )
    .await
    .unwrap();
    let project: i64 = sqlx::query_scalar("SELECT id FROM todo_projects WHERE user_id=$1")
        .bind(owner)
        .fetch_one(&pool)
        .await
        .unwrap();
    app::action(
        &pool,
        owner,
        "/todos",
        "POST",
        &json!({"title":"T","status":"todo","priority":"medium","project_id":project,
                "agent_repo_id":"r-api","agent_repo_name":"api","agent_auto":true}),
    )
    .await
    .unwrap();
    let todo: i64 = sqlx::query_scalar("SELECT id FROM todos WHERE user_id=$1")
        .bind(owner)
        .fetch_one(&pool)
        .await
        .unwrap();

    // Same workspace: the task keeps its repo and auto flag.
    app::action(
        &pool,
        owner,
        &format!("/todos/projects/{project}"),
        "PUT",
        &json!({"name":"P2","agent_workspace_id":"ws-1","agent_workspace_name":"Work"}),
    )
    .await
    .unwrap();
    let kept: (Option<String>, bool) =
        sqlx::query_as("SELECT agent_repo_id,agent_auto FROM todos WHERE id=$1")
            .bind(todo)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(kept, (Some("r-api".into()), true));

    // Different workspace: repo and auto are unpicked, nothing else changes.
    app::action(
        &pool,
        owner,
        &format!("/todos/projects/{project}"),
        "PUT",
        &json!({"name":"P2","agent_workspace_id":"ws-2","agent_workspace_name":"Home"}),
    )
    .await
    .unwrap();
    let cleared: (Option<String>, Option<String>, bool) =
        sqlx::query_as("SELECT agent_repo_id,agent_repo_name,agent_auto FROM todos WHERE id=$1")
            .bind(todo)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(cleared, (None, None, false));

    // Auto without a project is refused by the database as well as by validation.
    let refused = app::action(
        &pool,
        owner,
        "/todos",
        "POST",
        &json!({"title":"Loose","status":"todo","priority":"low","agent_repo_id":"r-api","agent_auto":true}),
    )
    .await;
    assert!(matches!(refused, Err(Error::Validation(_))));
    pool.close().await;
}
