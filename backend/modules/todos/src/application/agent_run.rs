//! Phase 4: claim, lease and report for Agent runs. Called with the Agent's `tdi_` token.
//! Every write takes the per-user lock first, like the browser actions, so the two never interleave.
use crate::{
    application::{Error as AppError, agent_link::AgentLinkConfig},
    domain::{
        self, Errors,
        agent_run::{
            self as run, AgentState, Checked, ClaimRequest, PrUpdateInput, Report, ResultView, Step,
        },
    },
    infrastructure::{self as repo, Tx},
};
use chrono::{DateTime, Utc};
use serde_json::{Value, json};
use sqlx::PgPool;
use uuid::Uuid;

#[derive(Debug)]
pub enum RunError {
    Db(sqlx::Error),
    Validation(Errors),
    NotFound,
    /// The claim id does not match the task's live claim (or the claim was already released).
    Conflict,
    /// The task no longer qualifies for the Agent (user changed status or turned automation off).
    Gone,
}

pub type RunResult<T> = Result<T, RunError>;

impl From<sqlx::Error> for RunError {
    fn from(e: sqlx::Error) -> Self {
        Self::Db(e)
    }
}
impl From<Errors> for RunError {
    fn from(e: Errors) -> Self {
        Self::Validation(e)
    }
}
impl From<AppError> for RunError {
    fn from(e: AppError) -> Self {
        match e {
            AppError::Db(e) => Self::Db(e),
            AppError::Validation(e) => Self::Validation(e),
            AppError::NotFound => Self::NotFound,
        }
    }
}
impl std::fmt::Display for RunError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            Self::Db(e) => write!(f, "database error: {e}"),
            Self::Validation(e) => write!(f, "validation failed: {e:?}"),
            Self::NotFound => write!(f, "not found"),
            Self::Conflict => write!(f, "claim mismatch"),
            Self::Gone => write!(f, "cancelled"),
        }
    }
}
impl std::error::Error for RunError {}

/// The task row, locked for update. `eligible` is the same rule the claim query uses.
#[derive(Debug, sqlx::FromRow)]
struct Row {
    status: String,
    agent_state: String,
    agent_claim_id: Option<Uuid>,
    agent_branch: Option<String>,
    agent_chat_url: Option<String>,
    agent_pr_url: Option<String>,
    description: Option<String>,
    eligible: bool,
    pause_requested: bool,
}

const LOCK_TASK: &str = "SELECT t.status,t.agent_state,t.agent_claim_id,t.agent_branch,t.agent_chat_url,t.agent_pr_url,t.description,t.agent_pause_requested AS pause_requested,(t.deleted_at IS NULL AND t.agent_auto AND t.project_id IS NOT NULL AND t.status IN ('todo','in_progress')) AS eligible FROM todos t WHERE t.user_id=$1 AND t.id=$2 FOR UPDATE OF t";

async fn lock_task(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> RunResult<Row> {
    sqlx::query_as::<_, Row>(LOCK_TASK)
        .bind(owner)
        .bind(id)
        .fetch_optional(&mut **tx)
        .await?
        .ok_or(RunError::NotFound)
}

fn state_of(row: &Row) -> AgentState {
    AgentState::parse(&row.agent_state).unwrap_or(AgentState::Idle)
}

fn check_claim(row: &Row, claim: Uuid) -> RunResult<()> {
    if row.agent_claim_id == Some(claim) {
        Ok(())
    } else {
        Err(RunError::Conflict)
    }
}

async fn event(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: i64,
    kind: &str,
    message: Option<&str>,
) -> RunResult<()> {
    let message: Option<String> = message.map(|m| m.chars().take(run::TEXT_MAX_CHARS).collect());
    sqlx::query("INSERT INTO todo_agent_events(user_id,todo_id,kind,message) VALUES($1,$2,$3,$4)")
        .bind(owner)
        .bind(id)
        .bind(kind)
        .bind(message)
        .execute(&mut **tx)
        .await?;
    Ok(())
}

/// Live claim whose task stopped qualifying: mark it cancelled. The claim id is kept, so the
/// Agent's next call gets 410 (not 409) and knows why.
async fn cancel_live(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> RunResult<()> {
    let affected = sqlx::query("UPDATE todos SET agent_state='cancelled',agent_lease_until=NULL WHERE user_id=$1 AND id=$2 AND agent_state IN ('claimed','running')")
        .bind(owner)
        .bind(id)
        .execute(&mut **tx)
        .await?
        .rows_affected();
    if affected > 0 {
        event(
            tx,
            owner,
            id,
            "cancelled",
            Some("Công việc đã đổi trạng thái hoặc tắt tự động xử lý trên Todo."),
        )
        .await?;
    }
    Ok(())
}

/// Run after every browser action, before commit. A live claim on a task that no longer
/// qualifies (status moved, automation off, deleted, repo removed) is cancelled in one statement.
/// A paused or live task that was closed (done/rejected) or deleted also queues a cleanup of the
/// Agent's clone: `discard` (rejected/deleted) closes the PR and deletes the branch too.
pub async fn guard_live_claims(tx: &mut Tx<'_>, owner: Uuid) -> crate::application::Result<()> {
    sqlx::query(
        "WITH c AS (UPDATE todos SET agent_state='cancelled',agent_lease_until=NULL,agent_pause_requested=false,agent_cleanup=CASE WHEN status='done' AND deleted_at IS NULL THEN 'keep' ELSE 'discard' END WHERE user_id=$1 AND agent_state IN ('claimed','running','paused') AND (deleted_at IS NOT NULL OR status IN ('done','rejected')) RETURNING id) INSERT INTO todo_agent_events(user_id,todo_id,kind,message) SELECT $1,id,'cleanup','Công việc đã đóng: Agent sẽ dọn bản sao và nhánh dang dở.' FROM c",
    )
    .bind(owner)
    .execute(&mut **tx)
    .await?;
    sqlx::query(
        "WITH c AS (UPDATE todos SET agent_state='cancelled',agent_lease_until=NULL WHERE user_id=$1 AND agent_state IN ('claimed','running') AND NOT (deleted_at IS NULL AND agent_auto AND project_id IS NOT NULL AND status IN ('todo','in_progress')) RETURNING id) INSERT INTO todo_agent_events(user_id,todo_id,kind,message) SELECT $1,id,'cancelled','Công việc đã đổi trạng thái hoặc tắt tự động xử lý trên Todo.' FROM c",
    )
    .bind(owner)
    .execute(&mut **tx)
    .await?;
    Ok(())
}

/// Link to the task on Todo, by its identifier (`/tasks/DEF-12`).
fn todo_url(config: &AgentLinkConfig, identifier: &str) -> Value {
    if config.todo_web_url.is_empty() {
        Value::Null
    } else {
        json!(format!("{}/tasks/{identifier}", config.todo_web_url))
    }
}

// ---- Claim -------------------------------------------------------------------------------------

#[derive(Debug, sqlx::FromRow)]
struct Candidate {
    id: i64,
    attempts: i32,
    state: String,
    branch: Option<String>,
    pr_url: Option<String>,
    pr_has_conflict: bool,
    result_md: Option<String>,
}

/// Same eligibility and order as `run::claim_key`. `FOR UPDATE SKIP LOCKED` keeps two pollers
/// from taking the same row.
const CANDIDATES: &str = "SELECT t.id,t.agent_attempts AS attempts,t.agent_state AS state,t.agent_branch AS branch,t.agent_pr_url AS pr_url,t.agent_pr_has_conflict AS pr_has_conflict,t.agent_result_md AS result_md FROM todos t JOIN todo_projects p ON p.user_id=t.user_id AND p.id=t.project_id AND p.deleted_at IS NULL WHERE t.user_id=$1 AND p.agent_workspace_id=ANY($2::text[]) AND t.agent_auto AND t.status='todo' AND t.deleted_at IS NULL AND t.agent_state NOT IN ('claimed','running','paused') AND NOT EXISTS (SELECT 1 FROM todo_dependencies d JOIN todos b ON b.user_id=d.user_id AND b.id=d.depends_on_id WHERE d.user_id=t.user_id AND d.todo_id=t.id AND (b.status<>'done' OR b.deleted_at IS NOT NULL)) ORDER BY CASE WHEN t.is_urgent AND t.is_important THEN 0 WHEN t.is_important THEN 1 WHEN t.is_urgent THEN 2 ELSE 3 END, CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, t.due_at NULLS LAST, t.created_at, t.id LIMIT $3 FOR UPDATE OF t SKIP LOCKED";

#[derive(Debug, sqlx::FromRow)]
struct Detail {
    identifier: String,
    title: String,
    description: Option<String>,
    priority: String,
    due_at: Option<DateTime<Utc>>,
    is_urgent: bool,
    is_important: bool,
    agent_repo_id: Option<String>,
    agent_repo_name: Option<String>,
    lease_until: Option<DateTime<Utc>>,
    project_id: i64,
    project_name: String,
    project_workspace: Option<String>,
    agent_base_branch: Option<String>,
}

const DETAIL: &str = "SELECT t.identifier,t.title,t.description,t.priority,t.due_at,t.is_urgent,t.is_important,t.agent_repo_id,t.agent_repo_name,t.agent_lease_until AS lease_until,t.agent_base_branch,p.id AS project_id,p.name AS project_name,p.agent_workspace_id AS project_workspace FROM todos t JOIN todo_projects p ON p.user_id=t.user_id AND p.id=t.project_id WHERE t.user_id=$1 AND t.id=$2";

/// Hand out up to `req.limit` tasks, each with its own claim id and lease.
pub async fn claim(
    pool: &PgPool,
    config: &AgentLinkConfig,
    owner: Uuid,
    req: &ClaimRequest,
) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let candidates: Vec<Candidate> = sqlx::query_as(CANDIDATES)
        .bind(owner)
        .bind(&req.workspaces)
        .bind(req.limit as i64)
        .fetch_all(&mut *tx)
        .await?;
    let mut tasks = Vec::with_capacity(candidates.len());
    for c in candidates {
        let claim_id = Uuid::new_v4();
        sqlx::query("UPDATE todos SET agent_state='claimed',agent_pause_requested=false,agent_cleanup=NULL,agent_claim_id=$3,agent_lease_until=now()+make_interval(secs=>$4),agent_attempts=agent_attempts+1 WHERE user_id=$1 AND id=$2")
            .bind(owner)
            .bind(c.id)
            .bind(claim_id)
            .bind(req.lease_secs as f64)
            .execute(&mut *tx)
            .await?;
        event(&mut tx, owner, c.id, "claimed", None).await?;
        let d: Detail = sqlx::query_as(DETAIL)
            .bind(owner)
            .bind(c.id)
            .fetch_one(&mut *tx)
            .await?;
        let deps: Vec<Value> = sqlx::query_scalar("SELECT jsonb_build_object('id',b.id,'identifier',b.identifier,'title',b.title,'pr_url',b.agent_pr_url) FROM todo_dependencies x JOIN todos b ON b.user_id=x.user_id AND b.id=x.depends_on_id WHERE x.user_id=$1 AND x.todo_id=$2 ORDER BY b.id")
            .bind(owner)
            .bind(c.id)
            .fetch_all(&mut *tx)
            .await?;
        // Previous run on this task: the branch and PR are reused (D7), and the failure text tells the
        // Agent why the last run stopped, even when it failed before any branch existed.
        let previous = (c.attempts > 0
            && (c.branch.is_some() || c.pr_url.is_some() || c.result_md.is_some()))
        .then(|| {
            json!({
                "branch": c.branch,
                "pr_url": c.pr_url,
                "agent_state": c.state,
                "has_conflict": c.pr_has_conflict,
                "result_md": c.result_md,
            })
        });
        tasks.push(json!({
            "id": c.id,
            "claim_id": claim_id,
            "lease_until": d.lease_until,
            "quadrant": domain::quadrant(d.is_urgent, d.is_important),
            "priority": d.priority,
            "due_at": d.due_at,
            "identifier": d.identifier,
            "title": d.title,
            "description": run::strip_result(d.description.as_deref().unwrap_or("")),
            "project": {"id": d.project_id, "name": d.project_name, "agent_workspace_id": d.project_workspace},
            // `null`: no repo. The Agent clones every repo of the workspace, picks the related
            // ones and opens one PR per changed repo (`pr_url` is the first).
            "repo": d.agent_repo_id.as_ref().map(|id| json!({"agent_repo_id": id, "name": d.agent_repo_name})),
            // `null` = the repo's default base on Agent.
            "base_branch": d.agent_base_branch,
            "depends_on": deps,
            "todo_url": todo_url(config, &d.identifier),
            "attempt": c.attempts + 1,
            "previous": previous,
        }));
    }
    if !tasks.is_empty() {
        repo::bump(&mut tx, owner).await?;
    }
    tx.commit().await?;
    Ok(json!({"tasks": tasks}))
}

// ---- Heartbeat / release -------------------------------------------------------------------------

pub async fn heartbeat(pool: &PgPool, owner: Uuid, id: i64, claim: Uuid) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let row = lock_task(&mut tx, owner, id).await?;
    check_claim(&row, claim)?;
    let current = state_of(&row);
    if !row.eligible && (current.is_live() || current == AgentState::Cancelled) {
        cancel_live(&mut tx, owner, id).await?;
        tx.commit().await?;
        return Err(RunError::Gone);
    }
    if !current.is_live() {
        return Err(RunError::Conflict);
    }
    let until: DateTime<Utc> = sqlx::query_scalar("UPDATE todos SET agent_lease_until=now()+make_interval(secs=>$3) WHERE user_id=$1 AND id=$2 RETURNING agent_lease_until")
        .bind(owner)
        .bind(id)
        .bind(run::LEASE_DEFAULT_SECS as f64)
        .fetch_one(&mut *tx)
        .await?;
    tx.commit().await?;
    // The Agent pauses the run when the user asked for it on Todo.
    Ok(json!({"lease_until": until, "pause_requested": row.pause_requested}))
}

/// Agent gives the task back (for example its setting was turned off mid-run).
pub async fn release(pool: &PgPool, owner: Uuid, id: i64, claim: Uuid) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let row = lock_task(&mut tx, owner, id).await?;
    check_claim(&row, claim)?;
    if !state_of(&row).is_live() {
        return Err(RunError::Conflict);
    }
    let status: String = sqlx::query_scalar("UPDATE todos SET agent_state='idle',agent_claim_id=NULL,agent_lease_until=NULL,status=CASE WHEN status='in_progress' THEN 'todo' ELSE status END,updated_at=now() WHERE user_id=$1 AND id=$2 RETURNING status")
        .bind(owner)
        .bind(id)
        .fetch_one(&mut *tx)
        .await?;
    event(
        &mut tx,
        owner,
        id,
        "released",
        Some("Agent trả công việc về hàng chờ."),
    )
    .await?;
    repo::bump(&mut tx, owner).await?;
    tx.commit().await?;
    Ok(json!({"agent_state": "idle", "status": status}))
}

// ---- Report ---------------------------------------------------------------------------------------

fn result_text(
    row_branch: Option<&str>,
    chat: Option<&str>,
    pr: Option<&str>,
    r: &Checked,
    headline: &str,
    error: Option<&str>,
    failed_step: Option<&str>,
) -> String {
    run::render_result(&ResultView {
        headline,
        branch: r.branch.as_deref().or(row_branch),
        chat_url: r.chat_url.as_deref().or(chat),
        pr_url: pr,
        has_conflict: r.has_conflict,
        conflict_files: &r.conflict_files,
        failed_step,
        error,
        summary: r.summary.as_deref(),
    })
}

pub async fn report(pool: &PgPool, owner: Uuid, id: i64, r: Checked) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let row = lock_task(&mut tx, owner, id).await?;
    check_claim(&row, r.claim_id)?;
    let current = state_of(&row);
    let target = match run::step(current, r.report) {
        // Same terminal state again: nothing to do, the first answer stands.
        Some(Step::Repeat) => {
            return Ok(json!({"agent_state": current.as_str(), "status": row.status}));
        }
        Some(Step::Move(target)) => target,
        // Not a valid move. A run the Todo side stopped (live claim, or already cancelled) gets 410;
        // a finished run reported again differently is a plain conflict.
        None if !row.eligible && (current.is_live() || current == AgentState::Cancelled) => {
            return Err(RunError::Gone);
        }
        None => return Err(RunError::Conflict),
    };
    if !row.eligible {
        if current.is_live() {
            cancel_live(&mut tx, owner, id).await?;
            tx.commit().await?;
        }
        return Err(RunError::Gone);
    }
    let status = apply_report(&mut tx, owner, id, &row, r).await?;
    repo::bump(&mut tx, owner).await?;
    tx.commit().await?;
    Ok(json!({"agent_state": target.as_str(), "status": status}))
}

async fn apply_report(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: i64,
    row: &Row,
    r: Checked,
) -> RunResult<String> {
    let lease = run::LEASE_DEFAULT_SECS as f64;
    match r.report {
        Report::Running => {
            let status: String = sqlx::query_scalar("UPDATE todos SET agent_state='running',agent_lease_until=now()+make_interval(secs=>$3),agent_chat_url=COALESCE($4,agent_chat_url),agent_branch=COALESCE($5,agent_branch),status=CASE WHEN status='todo' THEN 'in_progress' ELSE status END,started_at=COALESCE(started_at,now()),updated_at=now() WHERE user_id=$1 AND id=$2 RETURNING status")
                .bind(owner)
                .bind(id)
                .bind(lease)
                .bind(&r.chat_url)
                .bind(&r.branch)
                .fetch_one(&mut **tx)
                .await?;
            event(
                tx,
                owner,
                id,
                "running",
                Some(r.message.as_deref().unwrap_or("Agent bắt đầu chạy.")),
            )
            .await?;
            Ok(status)
        }
        Report::Progress => {
            sqlx::query("UPDATE todos SET agent_lease_until=now()+make_interval(secs=>$3) WHERE user_id=$1 AND id=$2")
                .bind(owner)
                .bind(id)
                .bind(lease)
                .execute(&mut **tx)
                .await?;
            event(tx, owner, id, "progress", r.message.as_deref()).await?;
            Ok(row.status.clone())
        }
        Report::Paused => {
            let md = result_text(
                row.agent_branch.as_deref(),
                row.agent_chat_url.as_deref(),
                row.agent_pr_url.as_deref(),
                &r,
                "⏸️ Tạm ngưng",
                None,
                None,
            );
            let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
            // The task stays in progress; claims skip it until it is resumed.
            let status: String = sqlx::query_scalar("UPDATE todos SET agent_state='paused',agent_pause_requested=false,agent_lease_until=NULL,agent_branch=COALESCE($3,agent_branch),agent_chat_url=COALESCE($4,agent_chat_url),agent_result_md=$5,description=$6,status=CASE WHEN status='todo' THEN 'in_progress' ELSE status END,updated_at=now() WHERE user_id=$1 AND id=$2 RETURNING status")
                .bind(owner)
                .bind(id)
                .bind(&r.branch)
                .bind(&r.chat_url)
                .bind(&md)
                .bind(description)
                .fetch_one(&mut **tx)
                .await?;
            event(tx, owner, id, "paused", r.message.as_deref()).await?;
            Ok(status)
        }
        Report::Succeeded => {
            let pr = r.pr_url.as_deref();
            let md = result_text(
                row.agent_branch.as_deref(),
                row.agent_chat_url.as_deref(),
                pr,
                &r,
                "✅ Thành công",
                None,
                None,
            );
            let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
            sqlx::query("UPDATE todos SET agent_state='succeeded',status='in_review',agent_lease_until=NULL,agent_pr_url=$3,agent_pr_has_conflict=$4,agent_branch=COALESCE($5,agent_branch),agent_chat_url=COALESCE($6,agent_chat_url),agent_result_md=$7,description=$8,updated_at=now() WHERE user_id=$1 AND id=$2")
                .bind(owner)
                .bind(id)
                .bind(pr)
                .bind(r.has_conflict)
                .bind(&r.branch)
                .bind(&r.chat_url)
                .bind(&md)
                .bind(description)
                .execute(&mut **tx)
                .await?;
            event(tx, owner, id, "succeeded", pr).await?;
            Ok("in_review".into())
        }
        Report::Failed => {
            let error = failure_text(&r);
            let md = result_text(
                row.agent_branch.as_deref(),
                row.agent_chat_url.as_deref(),
                row.agent_pr_url.as_deref(),
                &r,
                "❌ Thất bại",
                error.as_deref(),
                r.failed_step.as_deref(),
            );
            let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
            // A PR from an earlier run stays attached; a failed run never closes or replaces it.
            sqlx::query("UPDATE todos SET agent_state='failed',status='in_review',agent_lease_until=NULL,agent_branch=COALESCE($3,agent_branch),agent_chat_url=COALESCE($4,agent_chat_url),agent_result_md=$5,description=$6,updated_at=now() WHERE user_id=$1 AND id=$2")
                .bind(owner)
                .bind(id)
                .bind(&r.branch)
                .bind(&r.chat_url)
                .bind(&md)
                .bind(description)
                .execute(&mut **tx)
                .await?;
            event(tx, owner, id, "failed", error.as_deref()).await?;
            Ok("in_review".into())
        }
        Report::Cancelled => {
            let md = result_text(
                row.agent_branch.as_deref(),
                row.agent_chat_url.as_deref(),
                row.agent_pr_url.as_deref(),
                &r,
                "🛑 Đã hủy trên Agent",
                None,
                None,
            );
            let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
            sqlx::query("UPDATE todos SET agent_state='cancelled',status='in_review',agent_lease_until=NULL,agent_branch=COALESCE($3,agent_branch),agent_chat_url=COALESCE($4,agent_chat_url),agent_result_md=$5,description=$6,updated_at=now() WHERE user_id=$1 AND id=$2")
                .bind(owner)
                .bind(id)
                .bind(&r.branch)
                .bind(&r.chat_url)
                .bind(&md)
                .bind(description)
                .execute(&mut **tx)
                .await?;
            event(
                tx,
                owner,
                id,
                "cancelled",
                Some("Người dùng hủy trên Agent."),
            )
            .await?;
            Ok("in_review".into())
        }
    }
}

/// Error text for a failed report, including the "branch not pushed" note.
fn failure_text(r: &Checked) -> Option<String> {
    let mut parts = Vec::new();
    if let Some(e) = r.error.as_deref() {
        parts.push(e.to_owned());
    }
    if r.branch.is_some() && r.branch_pushed == Some(false) {
        parts.push("Nhánh chưa được push, chỉ có trên máy chủ Agent.".to_owned());
    }
    (!parts.is_empty()).then(|| parts.join(" "))
}

// ---- Pause / resume ----------------------------------------------------------------------------------

/// The user asks (on Todo) to pause a live run. The Agent sees it on its next heartbeat, stops the
/// run, pushes the work and reports `paused`.
pub async fn request_pause(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: i64,
) -> crate::application::Result<()> {
    let affected = sqlx::query("UPDATE todos SET agent_pause_requested=true WHERE user_id=$1 AND id=$2 AND deleted_at IS NULL AND agent_state IN ('claimed','running') AND NOT agent_pause_requested")
        .bind(owner)
        .bind(id)
        .execute(&mut **tx)
        .await?
        .rows_affected();
    if affected == 0 {
        return Err(AppError::field(
            "agent",
            "Agent không còn chạy task này hoặc đã được yêu cầu tạm ngưng.",
        ));
    }
    sqlx::query("INSERT INTO todo_agent_events(user_id,todo_id,kind,message) VALUES($1,$2,'pause_requested',NULL)")
        .bind(owner)
        .bind(id)
        .execute(&mut **tx)
        .await?;
    Ok(())
}

/// A paused task goes back to the queue (`todo`); the Agent continues on the same branch and chat.
pub async fn resume(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> crate::application::Result<()> {
    let ready: Option<bool> = sqlx::query_scalar("SELECT agent_auto AND project_id IS NOT NULL FROM todos WHERE user_id=$1 AND id=$2 AND deleted_at IS NULL AND agent_state='paused' FOR UPDATE")
        .bind(owner)
        .bind(id)
        .fetch_optional(&mut **tx)
        .await?;
    match ready {
        None => {
            return Err(AppError::field(
                "agent",
                "Task này không ở trạng thái tạm ngưng.",
            ));
        }
        Some(false) => {
            return Err(AppError::field(
                "agent",
                "Bật “Agent tự động xử lý” (cần project đã gắn workspace Agent) trước khi tiếp tục.",
            ));
        }
        Some(true) => {}
    }
    sqlx::query("UPDATE todos SET agent_state='idle',agent_pause_requested=false,status='todo',closed_at=NULL,updated_at=now() WHERE user_id=$1 AND id=$2")
        .bind(owner)
        .bind(id)
        .execute(&mut **tx)
        .await?;
    sqlx::query(
        "INSERT INTO todo_agent_events(user_id,todo_id,kind,message) VALUES($1,$2,'resumed',NULL)",
    )
    .bind(owner)
    .bind(id)
    .execute(&mut **tx)
    .await?;
    Ok(())
}

/// `POST /tasks/{id}/resume` from the Agent (its **Tiếp tục** button).
pub async fn agent_resume(pool: &PgPool, owner: Uuid, id: i64) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    resume(&mut tx, owner, id).await?;
    repo::bump(&mut tx, owner).await?;
    tx.commit().await?;
    Ok(json!({"agent_state": "idle", "status": "todo"}))
}

// ---- Cleanup queue ------------------------------------------------------------------------------------

/// Closed tasks whose clone (and, for `discard`, branch and PR) the Agent should remove.
pub async fn cleanups(pool: &PgPool, owner: Uuid) -> RunResult<Value> {
    let tasks: Vec<Value> = sqlx::query_scalar("SELECT jsonb_build_object('id',id,'mode',agent_cleanup,'branch',agent_branch,'pr_url',agent_pr_url) FROM todos WHERE user_id=$1 AND agent_cleanup IS NOT NULL ORDER BY id LIMIT 20")
        .bind(owner)
        .fetch_all(pool)
        .await?;
    Ok(json!({"tasks": tasks}))
}

/// The Agent finished one cleanup.
pub async fn cleaned(pool: &PgPool, owner: Uuid, id: i64) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let affected = sqlx::query(
        "UPDATE todos SET agent_cleanup=NULL WHERE user_id=$1 AND id=$2 AND agent_cleanup IS NOT NULL",
    )
    .bind(owner)
    .bind(id)
    .execute(&mut *tx)
    .await?
    .rows_affected();
    if affected > 0 {
        event(&mut tx, owner, id, "cleaned", None).await?;
        repo::bump(&mut tx, owner).await?;
    }
    tx.commit().await?;
    Ok(json!({"cleaned": affected > 0}))
}

// ---- PR updated outside the automatic run -----------------------------------------------------------

/// The chat's "Đẩy lên PR" (or a later push) changed the PR. Does not change the task status.
pub async fn pr_updated(
    pool: &PgPool,
    owner: Uuid,
    id: i64,
    input: PrUpdateInput,
) -> RunResult<Value> {
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let row = lock_task(&mut tx, owner, id).await?;
    if row.agent_branch.is_none() && row.agent_pr_url.is_none() {
        return Err(RunError::Conflict);
    }
    if state_of(&row).is_live() {
        return Err(RunError::Conflict);
    }
    let summary = input
        .summary_md
        .as_deref()
        .map(run::clean_summary)
        .filter(|s| !s.is_empty());
    let md = run::render_result(&ResultView {
        headline: "🔄 PR đã cập nhật",
        branch: row.agent_branch.as_deref(),
        chat_url: row.agent_chat_url.as_deref(),
        pr_url: Some(input.pr_url.as_str()),
        has_conflict: input.has_conflict,
        conflict_files: &input.conflict_files,
        failed_step: None,
        error: None,
        summary: summary.as_deref(),
    });
    let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
    sqlx::query("UPDATE todos SET agent_pr_url=$3,agent_pr_has_conflict=$4,agent_result_md=$5,description=$6,updated_at=now() WHERE user_id=$1 AND id=$2")
        .bind(owner)
        .bind(id)
        .bind(&input.pr_url)
        .bind(input.has_conflict)
        .bind(&md)
        .bind(description)
        .execute(&mut *tx)
        .await?;
    event(&mut tx, owner, id, "pr_updated", Some(&input.pr_url)).await?;
    repo::bump(&mut tx, owner).await?;
    tx.commit().await?;
    Ok(json!({"pr_url": input.pr_url, "pr_has_conflict": input.has_conflict, "status": row.status}))
}

// ---- Lease sweeper (console `todo:sweep-agent-leases`) --------------------------------------------

/// Expire live claims whose lease ran out. A task that still qualifies goes to `in_review` as a
/// failure; one that no longer qualifies is only marked cancelled. Each task is its own
/// transaction, so the per-user lock order matches the browser actions.
pub async fn sweep_expired(pool: &PgPool) -> RunResult<u64> {
    let expired: Vec<(Uuid, i64)> = sqlx::query_as("SELECT user_id,id FROM todos WHERE agent_state IN ('claimed','running') AND agent_lease_until < now()")
        .fetch_all(pool)
        .await?;
    let mut count = 0;
    for (owner, id) in expired {
        let mut tx = pool.begin().await?;
        repo::lock(&mut tx, owner).await?;
        let still: bool = sqlx::query_scalar("SELECT agent_state IN ('claimed','running') AND agent_lease_until < now() FROM todos WHERE user_id=$1 AND id=$2 FOR UPDATE")
            .bind(owner)
            .bind(id)
            .fetch_optional(&mut *tx)
            .await?
            .unwrap_or(false);
        if !still {
            continue;
        }
        let row = lock_task(&mut tx, owner, id).await?;
        if row.eligible {
            let error = "Agent mất kết nối hoặc quá thời gian (hết lease).";
            let md = run::render_result(&ResultView {
                headline: "❌ Thất bại",
                branch: row.agent_branch.as_deref(),
                chat_url: row.agent_chat_url.as_deref(),
                pr_url: row.agent_pr_url.as_deref(),
                has_conflict: false,
                conflict_files: &[],
                failed_step: Some("run"),
                error: Some(error),
                summary: None,
            });
            let description = domain::agent_run::with_result(row.description.as_deref(), Some(&md));
            sqlx::query("UPDATE todos SET agent_state='failed',status='in_review',agent_lease_until=NULL,agent_result_md=$3,description=$4,updated_at=now() WHERE user_id=$1 AND id=$2")
                .bind(owner)
                .bind(id)
                .bind(&md)
                .bind(description)
                .execute(&mut *tx)
                .await?;
            event(&mut tx, owner, id, "lease_expired", Some(error)).await?;
        } else {
            cancel_live(&mut tx, owner, id).await?;
        }
        repo::bump(&mut tx, owner).await?;
        tx.commit().await?;
        count += 1;
    }
    Ok(count)
}
