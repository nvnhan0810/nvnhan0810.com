use serde_json::{Value, json};
use sqlx::{Executor, postgres::PgPoolOptions};
use todos::{application as app, infrastructure as repo};
use uuid::Uuid;
#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn relational_crud_ownership_matrix_pomodoro_and_rollback() {
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
    tx.execute(sqlx::raw_sql(include_str!(
        "../../identity/migrations/20261030000001_identity.up.sql"
    )))
    .await
    .unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000002_todos.up.sql"
    )))
    .await
    .unwrap();
    tx.commit().await.unwrap();
    let owner = Uuid::new_v4();
    let other = Uuid::new_v4();
    for id in [owner, other] {
        sqlx::query(
            "INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Test')",
        )
        .bind(id)
        .bind(id.to_string())
        .bind(format!("{id}@example.com"))
        .execute(&pool)
        .await
        .unwrap();
    }
    app::action(&pool,owner,"/todos/projects","POST",&json!({"name":"Project","git_repo_url":"https://github.com/user/repo","domain":"https://example.com"})).await.unwrap();
    let project = app::page(&pool, owner, "/todos/projects").await.unwrap()["props"]["projects"][0]
        ["id"]
        .as_i64()
        .unwrap();
    let task = json!({"title":"Focus task","description":"Details","status":"backlog","priority":"urgent","project_id":project,"due_at":"2027-01-02T00:00:00+07:00","is_urgent":true,"is_important":true});
    assert!(
        app::action(&pool, other, "/todos", "POST", &task)
            .await
            .is_err()
    );
    app::action(&pool, owner, "/todos", "POST", &task)
        .await
        .unwrap();
    let matrix = repo::snapshot(&pool, owner).await.unwrap();
    let id = matrix["backlog"][0]["id"].as_i64().unwrap();
    assert!(
        app::action(&pool, other, &format!("/todos/{id}"), "DELETE", &json!({}))
            .await
            .is_err()
    );
    let promote = json!({"items":[{"id":id,"is_urgent":false,"is_important":true}]});
    app::action(&pool, owner, "/matrix/backlog/promote", "POST", &promote)
        .await
        .unwrap();
    assert!(
        app::action(&pool, owner, "/matrix/backlog/promote", "POST", &promote)
            .await
            .is_err()
    );
    let matrix = repo::snapshot(&pool, owner).await.unwrap();
    assert_eq!(matrix["quadrants"]["schedule"].as_array().unwrap().len(), 1);
    assert!(matrix["backlog"].as_array().unwrap().is_empty());
    let other_matrix = repo::snapshot(&pool, other).await.unwrap();
    assert!(
        other_matrix["quadrants"]["schedule"]
            .as_array()
            .unwrap()
            .is_empty()
    );
    assert!(
        app::action(
            &pool,
            other,
            "/matrix/pomodoro/start",
            "POST",
            &json!({"activeTodoId":id})
        )
        .await
        .is_err()
    );
    let start = app::action(
        &pool,
        owner,
        "/matrix/pomodoro/start",
        "POST",
        &json!({"activeTodoId":id}),
    )
    .await
    .unwrap();
    let again = app::action(&pool, owner, "/matrix/pomodoro/start", "POST", &json!({}))
        .await
        .unwrap();
    assert_eq!(
        start["runtime"]["sessionUuid"],
        again["runtime"]["sessionUuid"]
    );
    sqlx::query("UPDATE todo_pomodoro_states SET ends_at=0 WHERE user_id=$1")
        .bind(owner)
        .execute(&pool)
        .await
        .unwrap();
    // Concurrent reads reconcile a phase only once, while committing one durable event.
    let (first, second) = tokio::join!(repo::snapshot(&pool, owner), repo::snapshot(&pool, owner));
    assert_eq!(
        first.unwrap()["pomodoro"]["runtime"]["phase"],
        "short_break"
    );
    assert_eq!(
        second.unwrap()["pomodoro"]["runtime"]["phase"],
        "short_break"
    );
    let count: i64 = sqlx::query_scalar("SELECT count(*) FROM todo_push_events WHERE user_id=$1")
        .bind(owner)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(count, 1);
    repo::worker::tick(&pool, &Default::default())
        .await
        .unwrap();
    let delivered: bool = sqlx::query_scalar(
        "SELECT delivered_at IS NOT NULL FROM todo_push_events WHERE user_id=$1",
    )
    .bind(owner)
    .fetch_one(&pool)
    .await
    .unwrap();
    assert!(delivered);
    let paused = app::action(&pool, owner, "/matrix/pomodoro/pause", "POST", &json!({}))
        .await
        .unwrap();
    assert_eq!(paused["runtime"]["isRunning"], false);
    app::action(
        &pool,
        owner,
        &format!("/matrix/todos/{id}/complete"),
        "PATCH",
        &json!({}),
    )
    .await
    .unwrap();
    let done = app::page(&pool, owner, "/todos?status=done&search=Focus")
        .await
        .unwrap();
    assert_eq!(done["props"]["todos"]["total"], 1);
    assert!(!done["props"]["todos"]["data"][0]["closed_at"].is_null());
    let logs: i64 = sqlx::query_scalar("SELECT count(*) FROM eisenhower_logs WHERE user_id=$1")
        .bind(owner)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(logs, 2);
    app::action(
        &pool,
        owner,
        &format!("/todos/projects/{project}"),
        "DELETE",
        &json!({}),
    )
    .await
    .unwrap();
    let task = app::page(&pool, owner, &format!("/todos/{id}/edit"))
        .await
        .unwrap();
    assert!(task["props"]["todo"]["project_id"].is_null());
    app::action(&pool, owner, &format!("/todos/{id}"), "DELETE", &json!({}))
        .await
        .unwrap();
    assert!(
        app::page(&pool, owner, &format!("/todos/{id}/edit"))
            .await
            .is_err()
    );
    let active: Option<i64> =
        sqlx::query_scalar("SELECT active_todo_id FROM todo_pomodoro_states WHERE user_id=$1")
            .bind(owner)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert!(active.is_none());
    let document_columns:Vec<Value>=sqlx::query_scalar("SELECT to_jsonb(c) FROM information_schema.columns c WHERE table_schema=current_schema() AND table_name IN ('todo_projects','todos','eisenhower_logs','todo_pomodoro_states','web_push_subscriptions','todo_push_events','todo_push_deliveries','todo_user_states') AND data_type IN ('json','jsonb')").fetch_all(&pool).await.unwrap();
    assert!(document_columns.is_empty());
    let mut tx = pool.begin().await.unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000002_todos.down.sql"
    )))
    .await
    .unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000002_todos.up.sql"
    )))
    .await
    .unwrap();
    tx.commit().await.unwrap();
    pool.close().await;
    admin
        .execute(sqlx::AssertSqlSafe(format!("DROP SCHEMA {schema} CASCADE")))
        .await
        .unwrap();
    admin.close().await;
}
