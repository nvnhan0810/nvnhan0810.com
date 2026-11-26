//! Phase 4 against a real database. Needs a disposable PostgreSQL in TODO_TEST_DATABASE_URL.
use chrono::{DateTime, Duration, TimeZone, Utc};
use serde_json::{Value, json};
use sqlx::{Executor, PgPool, postgres::PgPoolOptions};
use todos::{
    application::{
        self as app,
        agent_link::AgentLinkConfig,
        agent_run::{self as runs, RunError},
    },
    domain::agent_run::{
        ClaimRequest, PrUpdateInput, RESULT_START, ReportInput, check_pr_update, check_report,
        claim_key, parse_claim,
    },
    infrastructure as repo,
};
use uuid::Uuid;

async fn database() -> PgPool {
    let url = std::env::var("TODO_TEST_DATABASE_URL").unwrap();
    let admin = PgPoolOptions::new()
        .max_connections(1)
        .connect(&url)
        .await
        .unwrap();
    let schema = format!("todo_run_{}", Uuid::new_v4().simple());
    admin
        .execute(sqlx::AssertSqlSafe(format!("CREATE SCHEMA {schema}")))
        .await
        .unwrap();
    let pool = PgPoolOptions::new()
        .max_connections(4)
        .after_connect(move |connection, _| {
            let schema = schema.clone();
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

fn config() -> AgentLinkConfig {
    let mut config =
        AgentLinkConfig::new("https://agent.example", "https://agent.example", [3; 32]);
    config.todo_web_url = "https://todo.example".into();
    config
}

fn at(minutes: i64) -> DateTime<Utc> {
    Utc.with_ymd_and_hms(2026, 11, 20, 0, 0, 0).unwrap() + Duration::minutes(minutes)
}

async fn user(pool: &PgPool) -> Uuid {
    let id = Uuid::new_v4();
    sqlx::query("INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Test')")
        .bind(id)
        .bind(id.to_string())
        .bind(format!("{id}@example.test"))
        .execute(pool)
        .await
        .unwrap();
    id
}

async fn project(pool: &PgPool, owner: Uuid, workspace: &str) -> i64 {
    sqlx::query_scalar(
        "INSERT INTO todo_projects(user_id,name,agent_workspace_id) VALUES($1,'P',$2) RETURNING id",
    )
    .bind(owner)
    .bind(workspace)
    .fetch_one(pool)
    .await
    .unwrap()
}

/// A live task that qualifies for the Agent unless the caller changes it.
struct Spec {
    urgent: bool,
    important: bool,
    priority: &'static str,
    due: Option<DateTime<Utc>>,
    created: DateTime<Utc>,
}

async fn task(pool: &PgPool, owner: Uuid, project: i64, spec: &Spec) -> i64 {
    sqlx::query_scalar("INSERT INTO todos(user_id,project_id,title,description,status,priority,due_at,is_urgent,is_important,created_at,agent_repo_id,agent_auto,identifier_seq) VALUES($1,$2,'T','desc','todo',$3,$4,$5,$6,$7,'repo-1',true,(SELECT COALESCE(MAX(identifier_seq),0)+1 FROM todos WHERE user_id=$1 AND identifier_prefix='DEF')) RETURNING id")
        .bind(owner)
        .bind(project)
        .bind(spec.priority)
        .bind(spec.due)
        .bind(spec.urgent)
        .bind(spec.important)
        .bind(spec.created)
        .fetch_one(pool)
        .await
        .unwrap()
}

async fn simple_task(pool: &PgPool, owner: Uuid, project: i64) -> i64 {
    task(
        pool,
        owner,
        project,
        &Spec {
            urgent: true,
            important: true,
            priority: "medium",
            due: None,
            created: at(0),
        },
    )
    .await
}

async fn claim_all(pool: &PgPool, owner: Uuid, workspaces: &[&str], limit: usize) -> Vec<Value> {
    let req = parse_claim(&json!({"workspaces": workspaces, "limit": limit})).unwrap();
    runs::claim(pool, &config(), owner, &req).await.unwrap()["tasks"]
        .as_array()
        .unwrap()
        .clone()
}

async fn claim_one(pool: &PgPool, owner: Uuid, id: i64) -> Uuid {
    let tasks = claim_all(pool, owner, &["ws-a"], 10).await;
    let found = tasks
        .iter()
        .find(|t| t["id"] == id)
        .unwrap_or_else(|| panic!("task {id} not claimed"));
    found["claim_id"].as_str().unwrap().parse().unwrap()
}

fn report_input(claim: Uuid, body: Value) -> ReportInput {
    let mut body = body;
    body["claim_id"] = json!(claim);
    serde_json::from_value(body).unwrap()
}

async fn send(
    pool: &PgPool,
    owner: Uuid,
    id: i64,
    claim: Uuid,
    body: Value,
) -> Result<Value, RunError> {
    let checked = check_report(report_input(claim, body)).expect("valid report");
    runs::report(pool, owner, id, checked).await
}

async fn state(pool: &PgPool, id: i64) -> (String, String) {
    sqlx::query_as("SELECT status,agent_state FROM todos WHERE id=$1")
        .bind(id)
        .fetch_one(pool)
        .await
        .unwrap()
}

async fn events(pool: &PgPool, id: i64) -> Vec<String> {
    sqlx::query_scalar("SELECT kind FROM todo_agent_events WHERE todo_id=$1 ORDER BY id")
        .bind(id)
        .fetch_all(pool)
        .await
        .unwrap()
}

async fn description(pool: &PgPool, id: i64) -> String {
    sqlx::query_scalar::<_, Option<String>>("SELECT description FROM todos WHERE id=$1")
        .bind(id)
        .fetch_one(pool)
        .await
        .unwrap()
        .unwrap_or_default()
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn claim_order_matches_the_domain_order_and_respects_limit_and_workspace() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p1 = project(&pool, owner, "ws-a").await;
    let p2 = project(&pool, owner, "ws-b").await;
    let day = 1440;
    let specs = [
        (
            p1,
            Spec {
                urgent: true,
                important: true,
                priority: "medium",
                due: None,
                created: at(0),
            },
        ),
        (
            p1,
            Spec {
                urgent: true,
                important: true,
                priority: "urgent",
                due: None,
                created: at(5),
            },
        ),
        (
            p1,
            Spec {
                urgent: true,
                important: true,
                priority: "urgent",
                due: Some(at(day)),
                created: at(10),
            },
        ),
        (
            p1,
            Spec {
                urgent: false,
                important: true,
                priority: "low",
                due: Some(at(3 * day)),
                created: at(1),
            },
        ),
        (
            p1,
            Spec {
                urgent: false,
                important: true,
                priority: "high",
                due: None,
                created: at(2),
            },
        ),
        (
            p1,
            Spec {
                urgent: true,
                important: false,
                priority: "urgent",
                due: None,
                created: at(3),
            },
        ),
        (
            p1,
            Spec {
                urgent: false,
                important: false,
                priority: "urgent",
                due: Some(at(day)),
                created: at(4),
            },
        ),
        (
            p2,
            Spec {
                urgent: true,
                important: true,
                priority: "urgent",
                due: None,
                created: at(6),
            },
        ),
    ];
    let mut rows: Vec<(i64, i64, &Spec)> = Vec::new();
    for (project, spec) in &specs {
        rows.push((task(&pool, owner, *project, spec).await, *project, spec));
    }
    // The order the Agent must see: the domain key, which the SQL has to reproduce.
    rows.sort_by_key(|(id, _, s)| {
        claim_key(s.urgent, s.important, s.priority, s.due, s.created, *id)
    });
    let ordered: Vec<i64> = rows.iter().map(|r| r.0).collect();
    let in_ws_a: Vec<i64> = rows.iter().filter(|r| r.1 == p1).map(|r| r.0).collect();

    let first = claim_all(&pool, owner, &["ws-a"], 3).await;
    let got: Vec<i64> = first.iter().map(|t| t["id"].as_i64().unwrap()).collect();
    assert_eq!(
        got,
        in_ws_a[..3].to_vec(),
        "limit and order inside one workspace"
    );
    assert_eq!(first[0]["quadrant"], "do");
    assert_eq!(
        first[0]["todo_url"],
        json!(format!("https://todo.example/tasks/{}", got[0]))
    );
    assert_eq!(first[0]["attempt"], 1);
    assert!(first[0]["previous"].is_null());

    let rest_expected: Vec<i64> = ordered
        .iter()
        .copied()
        .filter(|id| !got.contains(id))
        .collect();
    let rest = claim_all(&pool, owner, &["ws-a", "ws-b"], 10).await;
    let rest_got: Vec<i64> = rest.iter().map(|t| t["id"].as_i64().unwrap()).collect();
    assert_eq!(
        rest_got, rest_expected,
        "remaining tasks across both workspaces follow the domain order"
    );
    assert!(
        claim_all(&pool, owner, &["ws-a", "ws-b"], 10)
            .await
            .is_empty(),
        "claimed tasks are not handed out again"
    );
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn eligibility_and_dependencies_gate_the_claim() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    let a = simple_task(&pool, owner, p).await;
    let b = simple_task(&pool, owner, p).await;
    sqlx::query("INSERT INTO todo_dependencies(user_id,todo_id,depends_on_id) VALUES($1,$2,$3)")
        .bind(owner)
        .bind(b)
        .bind(a)
        .execute(&pool)
        .await
        .unwrap();
    let not_todo = simple_task(&pool, owner, p).await;
    sqlx::query("UPDATE todos SET status='backlog' WHERE id=$1")
        .bind(not_todo)
        .execute(&pool)
        .await
        .unwrap();
    let no_auto = simple_task(&pool, owner, p).await;
    sqlx::query("UPDATE todos SET agent_auto=false WHERE id=$1")
        .bind(no_auto)
        .execute(&pool)
        .await
        .unwrap();
    let deleted = simple_task(&pool, owner, p).await;
    sqlx::query("UPDATE todos SET deleted_at=now() WHERE id=$1")
        .bind(deleted)
        .execute(&pool)
        .await
        .unwrap();
    let rejected = simple_task(&pool, owner, p).await;
    sqlx::query("UPDATE todos SET status='rejected' WHERE id=$1")
        .bind(rejected)
        .execute(&pool)
        .await
        .unwrap();
    let blocked_by_rejected = simple_task(&pool, owner, p).await;
    sqlx::query("INSERT INTO todo_dependencies(user_id,todo_id,depends_on_id) VALUES($1,$2,$3)")
        .bind(owner)
        .bind(blocked_by_rejected)
        .bind(rejected)
        .execute(&pool)
        .await
        .unwrap();

    let first: Vec<i64> = claim_all(&pool, owner, &["ws-a"], 10)
        .await
        .iter()
        .map(|t| t["id"].as_i64().unwrap())
        .collect();
    assert_eq!(
        first,
        vec![a],
        "only A: B waits, the rest are not todo, not auto, deleted or blocked"
    );

    sqlx::query("UPDATE todos SET status='done' WHERE id=$1")
        .bind(a)
        .execute(&pool)
        .await
        .unwrap();
    let second: Vec<i64> = claim_all(&pool, owner, &["ws-a"], 10)
        .await
        .iter()
        .map(|t| t["id"].as_i64().unwrap())
        .collect();
    assert_eq!(
        second,
        vec![b],
        "B is handed out once A is done; a rejected dependency keeps its task blocked"
    );
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn concurrent_claims_never_hand_out_a_task_twice() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    for _ in 0..10 {
        simple_task(&pool, owner, p).await;
    }
    let mut handles = Vec::new();
    for _ in 0..5 {
        let pool = pool.clone();
        handles.push(tokio::spawn(async move {
            let req = ClaimRequest {
                workspaces: vec!["ws-a".into()],
                limit: 3,
                lease_secs: 900,
            };
            let out = runs::claim(&pool, &config(), owner, &req).await.unwrap();
            out["tasks"]
                .as_array()
                .unwrap()
                .iter()
                .map(|t| t["id"].as_i64().unwrap())
                .collect::<Vec<_>>()
        }));
    }
    let mut ids = Vec::new();
    for handle in handles {
        ids.extend(handle.await.unwrap());
    }
    let total = ids.len();
    ids.sort_unstable();
    ids.dedup();
    assert_eq!(ids.len(), total, "no task was claimed twice");
    assert!(total <= 10);
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn report_lifecycle_and_idempotency() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    let id = simple_task(&pool, owner, p).await;
    let claim = claim_one(&pool, owner, id).await;

    assert!(matches!(
        runs::heartbeat(&pool, owner, id, Uuid::new_v4()).await,
        Err(RunError::Conflict)
    ));
    runs::heartbeat(&pool, owner, id, claim).await.unwrap();

    send(&pool, owner, id, claim, json!({"state":"running","chat_url":"https://agent.example/chats/9","branch":"agent/todo-1-fix"})).await.unwrap();
    assert_eq!(
        state(&pool, id).await,
        ("in_progress".into(), "running".into())
    );
    send(
        &pool,
        owner,
        id,
        claim,
        json!({"state":"progress","message":"Đang test"}),
    )
    .await
    .unwrap();

    // A task with a repo needs a PR to succeed; the report is refused before the row changes.
    assert!(matches!(
        send(&pool, owner, id, claim, json!({"state":"succeeded"})).await,
        Err(RunError::Validation(_))
    ));
    assert_eq!(
        state(&pool, id).await,
        ("in_progress".into(), "running".into())
    );

    send(&pool, owner, id, claim, json!({"state":"succeeded","chat_url":"https://agent.example/chats/9","branch":"agent/todo-1-fix","pr_url":"https://github.com/o/r/pull/10","has_conflict":true,"conflict_files":["src/x.rs"],"summary_md":"Đã sửa."}))
        .await
        .unwrap();
    assert_eq!(
        state(&pool, id).await,
        ("in_review".into(), "succeeded".into())
    );
    let text = description(&pool, id).await;
    assert!(text.starts_with("desc\n\n"), "user text kept: {text}");
    assert!(
        text.contains("✅ Thành công") && text.contains("⚠️ Conflict với `main` ở `src/x.rs`"),
        "{text}"
    );
    assert_eq!(text.matches(RESULT_START).count(), 1);

    // Same terminal report again: 200 no-op. A different one after it: 409.
    send(
        &pool,
        owner,
        id,
        claim,
        json!({"state":"succeeded","pr_url":"https://github.com/o/r/pull/10"}),
    )
    .await
    .unwrap();
    assert!(matches!(
        send(&pool, owner, id, claim, json!({"state":"failed"})).await,
        Err(RunError::Conflict)
    ));
    assert!(matches!(
        runs::heartbeat(&pool, owner, id, claim).await,
        Err(RunError::Conflict)
    ));

    assert_eq!(
        events(&pool, id).await,
        vec!["claimed", "running", "progress", "succeeded"]
    );

    // The browser view never shows the block or the claim id.
    let mut tx = pool.begin().await.unwrap();
    let view = repo::task(&mut tx, owner, id).await.unwrap();
    tx.commit().await.unwrap();
    assert_eq!(view["description"], "desc");
    assert!(view.get("agent_claim_id").is_none());
    assert_eq!(view["agent_events"].as_array().unwrap().len(), 4);
    assert_eq!(view["agent_events"][0]["kind"], "succeeded", "newest first");
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn failed_run_then_rerun_hands_over_the_previous_branch_and_pr() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    let id = simple_task(&pool, owner, p).await;

    let claim = claim_one(&pool, owner, id).await;
    send(
        &pool,
        owner,
        id,
        claim,
        json!({"state":"running","branch":"agent/todo-1-fix"}),
    )
    .await
    .unwrap();
    send(&pool, owner, id, claim, json!({"state":"failed","branch":"agent/todo-1-fix","branch_pushed":false,"failed_step":"test","error":"cargo test thất bại"})).await.unwrap();
    assert_eq!(
        state(&pool, id).await,
        ("in_review".into(), "failed".into())
    );
    let text = description(&pool, id).await;
    assert!(
        text.contains("❌ Thất bại")
            && text.contains("cargo test thất bại")
            && text.contains("chưa được push"),
        "{text}"
    );

    // The user sends it back to todo. Automation is still on, so it is claimable again.
    sqlx::query("UPDATE todos SET status='todo' WHERE id=$1")
        .bind(id)
        .execute(&pool)
        .await
        .unwrap();
    let tasks = claim_all(&pool, owner, &["ws-a"], 1).await;
    assert_eq!(tasks[0]["attempt"], 2);
    assert_eq!(tasks[0]["previous"]["branch"], "agent/todo-1-fix");
    assert!(
        tasks[0]["previous"]["pr_url"].is_null(),
        "a failed run opens no PR"
    );
    assert_eq!(tasks[0]["previous"]["agent_state"], "failed");
    assert!(
        tasks[0]["previous"]["result_md"]
            .as_str()
            .unwrap()
            .contains("Thất bại")
    );
    let claim = tasks[0]["claim_id"]
        .as_str()
        .unwrap()
        .parse::<Uuid>()
        .unwrap();

    send(&pool, owner, id, claim, json!({"state":"succeeded","branch":"agent/todo-1-fix","pr_url":"https://github.com/o/r/pull/10"})).await.unwrap();
    // A PR changed outside the run: the task status stays where it is.
    let update: PrUpdateInput = serde_json::from_value(json!({"pr_url":"https://github.com/o/r/pull/10","has_conflict":true,"conflict_files":["src/x.rs"],"summary_md":"Đã đẩy tay."})).unwrap();
    let update = check_pr_update(update).unwrap();
    let out = runs::pr_updated(&pool, owner, id, update).await.unwrap();
    assert_eq!(out["status"], "in_review");
    assert_eq!(
        state(&pool, id).await,
        ("in_review".into(), "succeeded".into())
    );
    let (has_conflict,): (bool,) =
        sqlx::query_as("SELECT agent_pr_has_conflict FROM todos WHERE id=$1")
            .bind(id)
            .fetch_one(&pool)
            .await
            .unwrap();
    assert!(has_conflict);
    assert!(description(&pool, id).await.contains("🔄 PR đã cập nhật"));

    // A task that never ran has no PR to update.
    let never = simple_task(&pool, owner, p).await;
    let update = check_pr_update(
        serde_json::from_value(json!({"pr_url":"https://github.com/o/r/pull/11"})).unwrap(),
    )
    .unwrap();
    assert!(matches!(
        runs::pr_updated(&pool, owner, never, update).await,
        Err(RunError::Conflict)
    ));

    // Rerun after the PR: the previous run carries the PR and its conflict flag.
    sqlx::query("UPDATE todos SET status='todo' WHERE id=$1")
        .bind(id)
        .execute(&pool)
        .await
        .unwrap();
    let tasks = claim_all(&pool, owner, &["ws-a"], 1).await;
    assert_eq!(tasks[0]["attempt"], 3);
    assert_eq!(
        tasks[0]["previous"]["pr_url"],
        "https://github.com/o/r/pull/10"
    );
    assert_eq!(tasks[0]["previous"]["has_conflict"], true);
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn user_change_cancels_the_live_claim_and_the_agent_gets_410() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    let id = simple_task(&pool, owner, p).await;
    let claim = claim_one(&pool, owner, id).await;
    send(
        &pool,
        owner,
        id,
        claim,
        json!({"state":"running","branch":"agent/todo-1-fix"}),
    )
    .await
    .unwrap();

    // The user marks it done in the browser, through the same action path the UI uses.
    let data = json!({"title":"T","description":"desc","status":"done","priority":"medium","project_id":p,
        "is_urgent":true,"is_important":true,"agent_repo_id":"repo-1","agent_auto":true});
    app::action(&pool, owner, &format!("/todos/{id}"), "PUT", &data)
        .await
        .unwrap();

    assert!(matches!(
        runs::heartbeat(&pool, owner, id, claim).await,
        Err(RunError::Gone)
    ));
    let checked = check_report(report_input(
        claim,
        json!({"state":"succeeded","pr_url":"https://github.com/o/r/pull/10"}),
    ))
    .unwrap();
    assert!(matches!(
        runs::report(&pool, owner, id, checked).await,
        Err(RunError::Gone)
    ));
    assert_eq!(
        state(&pool, id).await,
        ("done".into(), "cancelled".into()),
        "status set by the user is kept"
    );
    assert!(events(&pool, id).await.contains(&"cancelled".to_string()));
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn expired_leases_become_in_review_unless_the_task_no_longer_qualifies() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;
    let running = simple_task(&pool, owner, p).await;
    let moved = simple_task(&pool, owner, p).await;
    let claimed = claim_all(&pool, owner, &["ws-a"], 10).await;
    let claim_running: Uuid =
        claimed.iter().find(|t| t["id"] == json!(running)).unwrap()["claim_id"]
            .as_str()
            .unwrap()
            .parse()
            .unwrap();
    assert!(claimed.iter().any(|t| t["id"] == json!(moved)));
    send(
        &pool,
        owner,
        running,
        claim_running,
        json!({"state":"running"}),
    )
    .await
    .unwrap();

    sqlx::query("UPDATE todos SET agent_lease_until=now()-interval '1 minute' WHERE id = ANY($1)")
        .bind(vec![running, moved])
        .execute(&pool)
        .await
        .unwrap();
    sqlx::query("UPDATE todos SET status='done' WHERE id=$1")
        .bind(moved)
        .execute(&pool)
        .await
        .unwrap();

    assert_eq!(runs::sweep_expired(&pool).await.unwrap(), 2);
    assert_eq!(
        state(&pool, running).await,
        ("in_review".into(), "failed".into())
    );
    assert!(description(&pool, running).await.contains("hết lease"));
    assert_eq!(
        state(&pool, moved).await,
        ("done".into(), "cancelled".into())
    );
    assert_eq!(
        runs::sweep_expired(&pool).await.unwrap(),
        0,
        "nothing left to expire"
    );
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn saving_keeps_the_result_block_and_the_claim_payload_matches_the_fixture() {
    let pool = database().await;
    let owner = user(&pool).await;
    let p = project(&pool, owner, "ws-a").await;

    // Dependency with a PR, and a task that already ran once, so the payload has every field.
    let dep = simple_task(&pool, owner, p).await;
    sqlx::query(
        "UPDATE todos SET status='done',agent_pr_url='https://github.com/o/r/pull/9' WHERE id=$1",
    )
    .bind(dep)
    .execute(&pool)
    .await
    .unwrap();
    let id = simple_task(&pool, owner, p).await;
    sqlx::query("INSERT INTO todo_dependencies(user_id,todo_id,depends_on_id) VALUES($1,$2,$3)")
        .bind(owner)
        .bind(id)
        .bind(dep)
        .execute(&pool)
        .await
        .unwrap();
    sqlx::query("UPDATE todos SET agent_attempts=1,agent_state='failed',agent_branch='agent/todo-1-fix',agent_pr_url='https://github.com/o/r/pull/10',agent_pr_has_conflict=true,agent_result_md='**🤖 Agent · ✅ Thành công**' WHERE id=$1").bind(id).execute(&pool).await.unwrap();
    let tasks = claim_all(&pool, owner, &["ws-a"], 1).await;
    let got = &tasks[0];
    let fixture: Value = serde_json::from_str(include_str!(
        "fixtures/integration/v1/claim_response.v1.json"
    ))
    .unwrap();
    let want = &fixture["tasks"][0];
    assert_eq!(keys(got), keys(want), "task keys");
    assert_eq!(
        keys(&got["project"]),
        keys(&want["project"]),
        "project keys"
    );
    assert_eq!(keys(&got["repo"]), keys(&want["repo"]), "repo keys");
    assert_eq!(
        keys(&got["depends_on"][0]),
        keys(&want["depends_on"][0]),
        "depends_on keys"
    );
    assert_eq!(
        keys(&got["previous"]),
        keys(&want["previous"]),
        "previous keys"
    );
    assert_eq!(
        got["depends_on"][0]["pr_url"],
        "https://github.com/o/r/pull/9"
    );

    // The Agent sees the user's text only, never the block.
    let claim = got["claim_id"].as_str().unwrap().parse::<Uuid>().unwrap();
    send(&pool, owner, id, claim, json!({"state":"running"}))
        .await
        .unwrap();
    send(&pool, owner, id, claim, json!({"state":"succeeded","pr_url":"https://github.com/o/r/pull/10","summary_md":"<!-- agent-result:end --> ok"})).await.unwrap();

    // The user saves the form, echoing the old description back. The block must survive, once.
    let data = json!({"title":"T","description":"Sửa mô tả","status":"in_review","priority":"medium","project_id":p,
        "is_urgent":true,"is_important":true,"agent_repo_id":"repo-1","agent_auto":true});
    app::action(&pool, owner, &format!("/todos/{id}"), "PUT", &data)
        .await
        .unwrap();
    let stored = description(&pool, id).await;
    assert!(stored.starts_with("Sửa mô tả\n\n"), "{stored}");
    assert_eq!(stored.matches(RESULT_START).count(), 1);
    assert_eq!(
        stored.matches("<!-- agent-result:end -->").count(),
        1,
        "summary cannot close the block early"
    );
    let mut tx = pool.begin().await.unwrap();
    let view = repo::task(&mut tx, owner, id).await.unwrap();
    tx.commit().await.unwrap();
    assert_eq!(view["description"], "Sửa mô tả");
}

fn keys(v: &Value) -> Vec<String> {
    let mut k: Vec<String> = v
        .as_object()
        .map(|o| o.keys().cloned().collect())
        .unwrap_or_default();
    k.sort();
    k
}

#[tokio::test]
#[ignore = "requires TODO_TEST_DATABASE_URL pointing to disposable PostgreSQL"]
async fn users_only_reach_their_own_tasks_and_release_returns_the_task() {
    let pool = database().await;
    let a = user(&pool).await;
    let b = user(&pool).await;
    let pa = project(&pool, a, "ws-a").await;
    let pb = project(&pool, b, "ws-a").await;
    let ta = simple_task(&pool, a, pa).await;
    simple_task(&pool, b, pb).await;

    let claim_a = claim_one(&pool, a, ta).await;
    let for_b = claim_all(&pool, b, &["ws-a"], 10).await;
    assert_eq!(for_b.len(), 1, "B only gets B's task");
    assert_ne!(for_b[0]["id"], json!(ta));
    assert!(matches!(
        runs::heartbeat(&pool, b, ta, claim_a).await,
        Err(RunError::NotFound)
    ));

    runs::release(&pool, a, ta, claim_a).await.unwrap();
    assert_eq!(state(&pool, ta).await, ("todo".into(), "idle".into()));
    assert!(matches!(
        runs::heartbeat(&pool, a, ta, claim_a).await,
        Err(RunError::Conflict)
    ));
    assert!(events(&pool, ta).await.contains(&"released".to_string()));
    // The released task is claimable again.
    assert_eq!(claim_all(&pool, a, &["ws-a"], 10).await.len(), 1);
}
