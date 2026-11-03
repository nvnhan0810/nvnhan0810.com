pub mod worker;
use crate::{
    application::{Error, Result},
    domain::{ProjectInput, TodoInput, pomodoro::State},
};
use serde_json::{Value, json};
use sqlx::{Postgres, Transaction};
use uuid::Uuid;
pub type Tx<'a> = Transaction<'a, Postgres>;
pub async fn lock(tx: &mut Tx<'_>, owner: Uuid) -> Result<i64> {
    sqlx::query("INSERT INTO todo_user_states(user_id) VALUES($1) ON CONFLICT DO NOTHING")
        .bind(owner)
        .execute(&mut **tx)
        .await?;
    Ok(
        sqlx::query_scalar("SELECT version FROM todo_user_states WHERE user_id=$1 FOR UPDATE")
            .bind(owner)
            .fetch_one(&mut **tx)
            .await?,
    )
}
pub async fn bump(tx: &mut Tx<'_>, owner: Uuid) -> Result<()> {
    sqlx::query("UPDATE todo_user_states SET version=version+1 WHERE user_id=$1")
        .bind(owner)
        .execute(&mut **tx)
        .await?;
    Ok(())
}
pub async fn projects(tx: &mut Tx<'_>, owner: Uuid) -> Result<Vec<Value>> {
    Ok(sqlx::query_scalar("SELECT (to_jsonb(p)-'user_id')||jsonb_build_object('todos_count',(SELECT count(*) FROM todos t WHERE t.user_id=$1 AND t.project_id=p.id AND t.deleted_at IS NULL)) FROM todo_projects p WHERE p.user_id=$1 AND p.deleted_at IS NULL ORDER BY p.name,p.id").bind(owner).fetch_all(&mut **tx).await?)
}
pub async fn tasks(tx: &mut Tx<'_>, owner: Uuid) -> Result<Vec<Value>> {
    Ok(sqlx::query_scalar("SELECT (to_jsonb(t)-'user_id')||jsonb_build_object('project',CASE WHEN p.id IS NOT NULL THEN jsonb_build_object('id',p.id,'name',p.name) END) FROM todos t LEFT JOIN todo_projects p ON p.id=t.project_id AND p.user_id=t.user_id AND p.deleted_at IS NULL WHERE t.user_id=$1 AND t.deleted_at IS NULL AND t.status IN ('backlog','todo','in_progress') ORDER BY CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,t.due_at NULLS LAST,t.created_at,t.id").bind(owner).fetch_all(&mut **tx).await?)
}
pub async fn ensure_task(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> Result<()> {
    let exists: bool = sqlx::query_scalar(
        "SELECT EXISTS(SELECT 1 FROM todos WHERE id=$1 AND user_id=$2 AND deleted_at IS NULL)",
    )
    .bind(id)
    .bind(owner)
    .fetch_one(&mut **tx)
    .await?;
    if exists { Ok(()) } else { Err(Error::NotFound) }
}
pub async fn ensure_project(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> Result<()> {
    let exists:bool=sqlx::query_scalar("SELECT EXISTS(SELECT 1 FROM todo_projects WHERE id=$1 AND user_id=$2 AND deleted_at IS NULL)").bind(id).bind(owner).fetch_one(&mut **tx).await?;
    if exists {
        Ok(())
    } else {
        Err(Error::field("project_id", "Project không tồn tại."))
    }
}
pub async fn log_flags(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: i64,
    urgent: bool,
    important: bool,
) -> Result<()> {
    sqlx::query("INSERT INTO eisenhower_logs(user_id,todo_id,is_urgent,is_important,created_by) VALUES($1,$2,$3,$4,$1)").bind(owner).bind(id).bind(urgent).bind(important).execute(&mut **tx).await?;
    Ok(())
}
pub async fn save_task(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: Option<i64>,
    d: &TodoInput,
) -> Result<i64> {
    if let Some(project) = d.project_id {
        ensure_project(tx, owner, project).await?;
    }
    let due = d
        .due_at
        .as_deref()
        .filter(|s| !s.is_empty())
        .and_then(crate::domain::parse_datetime);
    let result = if let Some(id) = id {
        ensure_task(tx, owner, id).await?;
        sqlx::query_scalar("UPDATE todos SET project_id=$3,title=$4,description=$5,status=$6,priority=$7,due_at=$8,is_urgent=$9,is_important=$10,started_at=CASE WHEN $6='in_progress' THEN COALESCE(started_at,now()) ELSE started_at END,closed_at=CASE WHEN $6 IN ('done','rejected') THEN COALESCE(closed_at,now()) ELSE NULL END,updated_at=now() WHERE user_id=$1 AND id=$2 RETURNING id").bind(owner).bind(id).bind(d.project_id).bind(&d.title).bind(&d.description).bind(&d.status).bind(&d.priority).bind(due).bind(d.is_urgent).bind(d.is_important).fetch_one(&mut **tx).await?
    } else {
        sqlx::query_scalar("INSERT INTO todos(user_id,project_id,title,description,status,priority,due_at,is_urgent,is_important,started_at,closed_at) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,CASE WHEN $5='in_progress' THEN now() END,CASE WHEN $5 IN ('done','rejected') THEN now() END) RETURNING id").bind(owner).bind(d.project_id).bind(&d.title).bind(&d.description).bind(&d.status).bind(&d.priority).bind(due).bind(d.is_urgent).bind(d.is_important).fetch_one(&mut **tx).await?
    };
    if !["todo", "in_progress"].contains(&d.status.as_str()) {
        sqlx::query("UPDATE todo_pomodoro_states SET active_todo_id=NULL,client_updated_at=GREATEST(client_updated_at+1,$3) WHERE user_id=$1 AND active_todo_id=$2").bind(owner).bind(result).bind(chrono::Utc::now().timestamp_millis()).execute(&mut **tx).await?;
    }
    log_flags(tx, owner, result, d.is_urgent, d.is_important).await?;
    bump(tx, owner).await?;
    Ok(result)
}
pub async fn save_project(
    tx: &mut Tx<'_>,
    owner: Uuid,
    id: Option<i64>,
    d: &ProjectInput,
) -> Result<i64> {
    let repo = d.git_repo_url.as_deref().filter(|s| !s.is_empty());
    let domain = d.domain.as_deref().filter(|s| !s.is_empty());
    let result = if let Some(id) = id {
        ensure_project(tx, owner, id).await?;
        sqlx::query_scalar("UPDATE todo_projects SET name=$3,git_repo_url=$4,domain=$5,updated_at=now() WHERE user_id=$1 AND id=$2 RETURNING id").bind(owner).bind(id).bind(&d.name).bind(repo).bind(domain).fetch_one(&mut **tx).await?
    } else {
        sqlx::query_scalar("INSERT INTO todo_projects(user_id,name,git_repo_url,domain) VALUES($1,$2,$3,$4) RETURNING id").bind(owner).bind(&d.name).bind(repo).bind(domain).fetch_one(&mut **tx).await?
    };
    bump(tx, owner).await?;
    Ok(result)
}
pub async fn pomodoro(tx: &mut Tx<'_>, owner: Uuid) -> Result<State> {
    sqlx::query("INSERT INTO todo_pomodoro_states(user_id) VALUES($1) ON CONFLICT DO NOTHING")
        .bind(owner)
        .execute(&mut **tx)
        .await?;
    let row: Value = sqlx::query_scalar(
        "SELECT to_jsonb(p) FROM todo_pomodoro_states p WHERE user_id=$1 FOR UPDATE",
    )
    .bind(owner)
    .fetch_one(&mut **tx)
    .await?;
    serde_json::from_value(row).map_err(|e| Error::Db(sqlx::Error::Decode(Box::new(e))))
}
pub async fn save_pomodoro(tx: &mut Tx<'_>, s: &State) -> Result<()> {
    sqlx::query("UPDATE todo_pomodoro_states SET focus_minutes=$2,short_break_minutes=$3,sessions_before_long_break=$4,long_break_minutes=$5,phase=$6,remaining_ms=$7,ends_at=$8,focus_count=$9,active_todo_id=$10,is_running=$11,session_uuid=$12,last_focused_at=$13,client_updated_at=$14 WHERE user_id=$1").bind(s.user_id).bind(s.focus_minutes).bind(s.short_break_minutes).bind(s.sessions_before_long_break).bind(s.long_break_minutes).bind(&s.phase).bind(s.remaining_ms).bind(s.ends_at).bind(s.focus_count).bind(s.active_todo_id).bind(s.is_running).bind(s.session_uuid).bind(s.last_focused_at).bind(s.client_updated_at).execute(&mut **tx).await?;
    Ok(())
}
pub async fn reconcile(tx: &mut Tx<'_>, s: &mut State, now: i64) -> Result<()> {
    if s.is_running && s.ends_at.is_some_and(|end| end <= now) {
        let from = s.phase.clone();
        let session = s
            .session_uuid
            .ok_or_else(|| Error::field("session", "Thiếu phiên Pomodoro."))?;
        s.advance(now);
        sqlx::query("INSERT INTO todo_push_events(user_id,session_uuid,from_phase,to_phase) VALUES($1,$2,$3,$4) ON CONFLICT(session_uuid) DO NOTHING").bind(s.user_id).bind(session).bind(from).bind(&s.phase).execute(&mut **tx).await?;
        save_pomodoro(tx, s).await?;
        bump(tx, s.user_id).await?;
    }
    Ok(())
}
pub async fn snapshot(pool: &sqlx::PgPool, owner: Uuid) -> Result<Value> {
    let mut tx = pool.begin().await?;
    let version = lock(&mut tx, owner).await?;
    let todos = tasks(&mut tx, owner).await?;
    let mut p = pomodoro(&mut tx, owner).await?;
    reconcile(&mut tx, &mut p, chrono::Utc::now().timestamp_millis()).await?;
    let mut quadrants = json!({"do":[],"schedule":[],"delegate":[],"eliminate":[]});
    let mut backlog = Vec::new();
    for t in todos {
        match t["status"].as_str() {
            Some("todo" | "in_progress") => {
                let key =
                    crate::domain::quadrant(t["is_urgent"] == true, t["is_important"] == true);
                quadrants[key].as_array_mut().unwrap().push(t)
            }
            Some("backlog") => backlog.push(t),
            _ => {}
        }
    }
    let projects = projects(&mut tx, owner).await?;
    tx.commit().await?;
    Ok(
        json!({"quadrants":quadrants,"backlog":backlog,"projects":projects,"pomodoro":p.payload(),"version":version,"statuses":crate::domain::STATUSES,"priorities":crate::domain::PRIORITIES}),
    )
}
pub async fn task(tx: &mut Tx<'_>, owner: Uuid, id: i64) -> Result<Value> {
    sqlx::query_scalar("SELECT (to_jsonb(t)-'user_id')||jsonb_build_object('project',CASE WHEN p.id IS NOT NULL THEN jsonb_build_object('id',p.id,'name',p.name) END) FROM todos t LEFT JOIN todo_projects p ON p.id=t.project_id AND p.user_id=t.user_id AND p.deleted_at IS NULL WHERE t.user_id=$1 AND t.id=$2 AND t.deleted_at IS NULL").bind(owner).bind(id).fetch_optional(&mut **tx).await?.ok_or(Error::NotFound)
}
pub async fn paginate(
    tx: &mut Tx<'_>,
    owner: Uuid,
    project: Option<i64>,
    status: &str,
    search: &str,
    page: i64,
) -> Result<Value> {
    let total:i64=sqlx::query_scalar("SELECT count(*) FROM todos t WHERE t.user_id=$1 AND t.deleted_at IS NULL AND ($2::bigint IS NULL OR t.project_id=$2) AND ($3::text='' OR t.status=$3) AND ($4::text='' OR strpos(lower(t.title),lower($4))>0 OR strpos(lower(COALESCE(t.description,'')),lower($4))>0)").bind(owner).bind(project).bind(status).bind(search).fetch_one(&mut **tx).await?;
    let last = ((total + 19) / 20).max(1);
    let page = page.clamp(1, last);
    let data:Vec<Value>=sqlx::query_scalar("SELECT (to_jsonb(t)-'user_id')||jsonb_build_object('project',CASE WHEN p.id IS NOT NULL THEN jsonb_build_object('id',p.id,'name',p.name) END) FROM todos t LEFT JOIN todo_projects p ON p.id=t.project_id AND p.user_id=t.user_id AND p.deleted_at IS NULL WHERE t.user_id=$1 AND t.deleted_at IS NULL AND ($2::bigint IS NULL OR t.project_id=$2) AND ($3::text='' OR t.status=$3) AND ($4::text='' OR strpos(lower(t.title),lower($4))>0 OR strpos(lower(COALESCE(t.description,'')),lower($4))>0) ORDER BY t.updated_at DESC,t.id DESC LIMIT 20 OFFSET $5").bind(owner).bind(project).bind(status).bind(search).bind((page-1)*20).fetch_all(&mut **tx).await?;
    Ok(json!({"data":data,"current_page":page,"last_page":last,"total":total}))
}
