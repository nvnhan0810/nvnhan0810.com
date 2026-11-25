//! Task identifiers (`PREFIX-N`) against a real database. Needs TODO_TEST_DATABASE_URL.
use serde_json::json;
use sqlx::{Executor, PgPool, postgres::PgPoolOptions};
use todos::{application as app, infrastructure as repo};
use uuid::Uuid;

async fn pool() -> PgPool {
    let url = std::env::var("TODO_TEST_DATABASE_URL").unwrap();
    let admin = PgPoolOptions::new()
        .max_connections(1)
        .connect(&url)
        .await
        .unwrap();
    let schema = format!("todo_ident_{}", Uuid::new_v4().simple());
    admin
        .execute(sqlx::AssertSqlSafe(format!("CREATE SCHEMA {schema}")))
        .await
        .unwrap();
    let search = schema.clone();
    let pool = PgPoolOptions::new()
        .max_connections(3)
        .after_connect(move |connection, _| {
            let search = search.clone();
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
    let mut tx = pool.begin().await.unwrap();
    for sql in [
        include_str!("../../identity/migrations/20261030000001_identity.up.sql"),
        include_str!("../migrations/20261103000002_todos.up.sql"),
        include_str!("../migrations/20261123000001_in_review_and_dependencies.up.sql"),
        include_str!("../migrations/20261123000002_agent_links.up.sql"),
        include_str!("../migrations/20261123000003_agent_targets.up.sql"),
        include_str!("../migrations/20261125000001_agent_runs.up.sql"),
        include_str!("../migrations/20261125000002_task_identifiers.up.sql"),
    ] {
        tx.execute(sqlx::raw_sql(sql)).await.unwrap();
    }
    tx.commit().await.unwrap();
    pool
}

async fn user(pool: &PgPool) -> Uuid {
    let id = Uuid::new_v4();
    sqlx::query("INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Test')")
        .bind(id)
        .bind(id.to_string())
        .bind(format!("{id}@example.com"))
        .execute(pool)
        .await
        .unwrap();
    id
}

async fn project(pool: &PgPool, owner: Uuid, name: &str, prefix: Option<&str>) -> i64 {
    app::action(
        pool,
        owner,
        "/todos/projects",
        "POST",
        &json!({"name": name, "identifier_prefix": prefix}),
    )
    .await
    .unwrap();
    sqlx::query_scalar("SELECT id FROM todo_projects WHERE user_id=$1 AND name=$2")
        .bind(owner)
        .bind(name)
        .fetch_one(pool)
        .await
        .unwrap()
}

/// Create a task and return its id and identifier.
async fn create(pool: &PgPool, owner: Uuid, title: &str, project: Option<i64>) -> (i64, String) {
    let body =
        json!({"title": title, "status": "backlog", "priority": "medium", "project_id": project});
    app::action(pool, owner, "/todos", "POST", &body)
        .await
        .unwrap();
    let row: (i64, String) = sqlx::query_as(
        "SELECT id,identifier FROM todos WHERE user_id=$1 AND title=$2 ORDER BY id DESC LIMIT 1",
    )
    .bind(owner)
    .bind(title)
    .fetch_one(pool)
    .await
    .unwrap();
    row
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn identifiers_follow_project_prefix_and_never_change() {
    let pool = pool().await;
    let owner = user(&pool).await;
    let other = user(&pool).await;

    // Default prefix is DEF; a project's prefix is normalized to upper case.
    let plain = project(&pool, owner, "Plain", None).await;
    let api = project(&pool, owner, "API work", Some("api")).await;
    let stored: String =
        sqlx::query_scalar("SELECT identifier_prefix FROM todo_projects WHERE id=$1")
            .bind(plain)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(stored, "DEF");
    let stored: String =
        sqlx::query_scalar("SELECT identifier_prefix FROM todo_projects WHERE id=$1")
            .bind(api)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(stored, "API");

    // Tasks without a project and tasks in a DEF project share one DEF counter.
    let (_, a) = create(&pool, owner, "no project", None).await;
    let (_, b) = create(&pool, owner, "plain project", Some(plain)).await;
    let (t_api, c) = create(&pool, owner, "api task", Some(api)).await;
    assert_eq!(
        (a.as_str(), b.as_str(), c.as_str()),
        ("DEF-1", "DEF-2", "API-1")
    );

    // Other users count from 1 again.
    let (_, o) = create(&pool, other, "other user", None).await;
    assert_eq!(o, "DEF-1");

    // Moving a task to another project keeps its identifier.
    app::action(
        &pool,
        owner,
        &format!("/todos/{t_api}"),
        "PUT",
        &json!({"title": "api task", "status": "backlog", "priority": "medium", "project_id": plain}),
    )
    .await
    .unwrap();
    let still: String = sqlx::query_scalar("SELECT identifier FROM todos WHERE id=$1")
        .bind(t_api)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(still, "API-1");

    // Changing the prefix affects only new tasks.
    app::action(
        &pool,
        owner,
        &format!("/todos/projects/{api}"),
        "PUT",
        &json!({"name": "API work", "identifier_prefix": "SVC"}),
    )
    .await
    .unwrap();
    let (_, d) = create(&pool, owner, "after rename", Some(api)).await;
    assert_eq!(d, "SVC-1");
    let first: String = sqlx::query_scalar("SELECT identifier FROM todos WHERE id=$1")
        .bind(t_api)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(first, "API-1");

    // Soft-deleted tasks keep their number: the next one does not reuse it.
    app::action(
        &pool,
        owner,
        &format!("/todos/{t_api}"),
        "DELETE",
        &json!({}),
    )
    .await
    .unwrap();
    let (_, e) = create(&pool, owner, "after delete", Some(plain)).await;
    assert_eq!(e, "DEF-3");

    // Search finds a task by its identifier, in any case.
    let mut tx = pool.begin().await.unwrap();
    let page = repo::paginate(&mut tx, owner, None, "", "def-2", 1)
        .await
        .unwrap();
    tx.commit().await.unwrap();
    assert_eq!(page["total"], 1);
    assert_eq!(page["data"][0]["identifier"], "DEF-2");
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn project_prefix_rejects_bad_values_at_the_action_layer() {
    let pool = pool().await;
    let owner = user(&pool).await;
    let err = app::action(
        &pool,
        owner,
        "/todos/projects",
        "POST",
        &json!({"name": "Bad", "identifier_prefix": "1-X"}),
    )
    .await
    .unwrap_err();
    assert!(matches!(err, app::Error::Validation(ref e) if e.contains_key("identifier_prefix")));
}
