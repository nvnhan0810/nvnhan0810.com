//! HTTP adapter for the Todo <-> Agent link (Phase 2).
use super::{HttpError, TodoState, owner};
use crate::application::agent_link::{self as link, AgentLinkConfig, CompleteInput, LinkError};
use axum::{
    Json, Router,
    extract::State,
    http::{HeaderMap, StatusCode},
    routing::{get, post},
};
use serde::Deserialize;
use serde_json::{Value, json};
use uuid::Uuid;

pub(super) fn error(e: LinkError) -> HttpError {
    (
        StatusCode::from_u16(e.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR),
        Json(json!({"error":e.code})),
    )
}
pub(super) fn http_client() -> reqwest::Client {
    reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(5))
        .redirect(reqwest::redirect::Policy::none())
        .build()
        .unwrap_or_default()
}

// ---- Browser (Identity access token) -------------------------------------------------------
pub(super) async fn status(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    link::status(&state.pool, &state.agent, user.id)
        .await
        .map(Json)
        .map_err(error)
}
pub(super) async fn start(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    link::start(&state.pool, &state.agent, user.id)
        .await
        .map(Json)
        .map_err(error)
}
pub(super) async fn unlink(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    let token = link::revoke(&state.pool, &state.agent, user.id)
        .await
        .map_err(error)?;
    state.catalogs.forget(user.id);
    let notified = match token {
        Some(token) => link::notify_agent_unlinked(&state.http, &state.agent, &token).await,
        None => true,
    };
    Ok(Json(json!({"linked":false,"agent_notified":notified})))
}

// ---- Agent server (tdi_ token) ---------------------------------------------------------------
#[derive(Clone)]
struct IntegrationState {
    pool: sqlx::PgPool,
    agent: AgentLinkConfig,
}
#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct CompleteBody {
    req: Uuid,
    code: String,
    identity_user_id: Uuid,
    email: String,
    agent_token: String,
}
async fn complete(
    State(state): State<IntegrationState>,
    Json(body): Json<CompleteBody>,
) -> Result<Json<Value>, HttpError> {
    let token = link::complete(
        &state.pool,
        &state.agent,
        CompleteInput {
            request_id: body.req,
            code: body.code,
            identity_user_id: body.identity_user_id,
            email: body.email,
            agent_token: body.agent_token,
        },
    )
    .await
    .map_err(error)?;
    Ok(Json(json!({"todo_token":token})))
}
fn bearer(headers: &HeaderMap) -> Option<&str> {
    headers.get("authorization").and_then(|v| v.to_str().ok())
}
async fn whoami(
    State(state): State<IntegrationState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let linked = link::authenticate(&state.pool, bearer(&headers))
        .await
        .map_err(error)?;
    Ok(Json(
        json!({"identity_user_id":linked.user_id,"email":linked.email}),
    ))
}
async fn agent_unlink(
    State(state): State<IntegrationState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let linked = link::authenticate(&state.pool, bearer(&headers))
        .await
        .map_err(error)?;
    // The Agent initiated this; no need to call it back.
    link::revoke(&state.pool, &state.agent, linked.user_id)
        .await
        .map_err(error)?;
    Ok(Json(json!({"linked":false})))
}
pub(super) fn integration_routes(pool: sqlx::PgPool, agent: AgentLinkConfig) -> Router {
    Router::new()
        .route("/link/complete", post(complete))
        .route("/link", get(whoami).delete(agent_unlink))
        .with_state(IntegrationState { pool, agent })
}

// ---- Catalog (proxy + cache) and project/task validation against it --------------------------
const CATALOG_TTL: std::time::Duration = std::time::Duration::from_secs(60);

/// Per-user catalog cache (60 s). Dropped on unlink.
#[derive(Clone, Default)]
pub struct CatalogCache(
    std::sync::Arc<
        std::sync::Mutex<std::collections::HashMap<Uuid, (std::time::Instant, link::Catalog)>>,
    >,
);
impl CatalogCache {
    fn get(&self, user: Uuid) -> Option<link::Catalog> {
        let map = self.0.lock().ok()?;
        map.get(&user)
            .filter(|(at, _)| at.elapsed() < CATALOG_TTL)
            .map(|(_, c)| c.clone())
    }
    fn put(&self, user: Uuid, catalog: link::Catalog) {
        if let Ok(mut map) = self.0.lock() {
            map.retain(|_, (at, _)| at.elapsed() < CATALOG_TTL);
            map.insert(user, (std::time::Instant::now(), catalog));
        }
    }
    pub(super) fn forget(&self, user: Uuid) {
        if let Ok(mut map) = self.0.lock() {
            map.remove(&user);
        }
    }
}
async fn catalog_for(
    state: &TodoState,
    user: Uuid,
    fresh: bool,
) -> link::LinkResult<link::Catalog> {
    if !fresh && let Some(hit) = state.catalogs.get(user) {
        return Ok(hit);
    }
    let catalog = link::fetch_catalog(&state.pool, &state.agent, &state.http, user).await?;
    state.catalogs.put(user, catalog.clone());
    Ok(catalog)
}
#[derive(Deserialize)]
pub(super) struct CatalogQuery {
    #[serde(default)]
    refresh: bool,
}
pub(super) async fn catalog(
    State(state): State<TodoState>,
    headers: HeaderMap,
    axum::extract::Query(q): axum::extract::Query<CatalogQuery>,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    if !state.agent.enabled {
        return Err(error(LinkError::new(503, "integration_disabled")));
    }
    let catalog = catalog_for(&state, user.id, q.refresh)
        .await
        .map_err(error)?;
    Ok(Json(json!({"workspaces":catalog.workspaces})))
}

fn invalid(field: &str, message: &str) -> HttpError {
    super::response(crate::application::Error::field(field, message))
}
fn db(e: sqlx::Error) -> HttpError {
    super::response(e.into())
}
fn unavailable(field: &str) -> HttpError {
    invalid(
        field,
        "Không lấy được danh sách từ Agent. Hãy kiểm tra kết nối rồi thử lại.",
    )
}
fn text(data: &Value, key: &str) -> Option<String> {
    crate::domain::clean_id(data.get(key).and_then(Value::as_str).map(str::to_owned))
}
fn path_id(path: &str, index: usize) -> Option<i64> {
    path.trim_matches('/').split('/').nth(index)?.parse().ok()
}

/// Server-side checks for the Agent fields of a project or task being saved. The names are taken
/// from the Agent catalog, never from the client. Offline Agent: an unchanged selection keeps its
/// stored snapshot, a new selection is refused.
pub(super) async fn enrich_action(
    state: &TodoState,
    user: Uuid,
    path: &str,
    method: &str,
    data: &mut Value,
) -> Result<(), HttpError> {
    if !matches!(method, "POST" | "PUT") || !data.is_object() {
        return Ok(());
    }
    let parts: Vec<&str> = path.trim_matches('/').split('/').collect();
    match (parts.first(), parts.get(1), parts.get(2)) {
        (Some(&"todos"), Some(&"projects"), _) => {
            enrich_project(state, user, path, method, data).await
        }
        (Some(&"todos"), _, Some(&"dependencies")) => Ok(()),
        (Some(&"todos"), _, _) if parts.len() <= 2 => {
            enrich_task(state, user, path, method, data).await
        }
        _ => Ok(()),
    }
}

async fn enrich_project(
    state: &TodoState,
    user: Uuid,
    path: &str,
    method: &str,
    data: &mut Value,
) -> Result<(), HttpError> {
    let wanted = text(data, "agent_workspace_id");
    let stored: Option<(Option<String>, Option<String>)> = match (method, path_id(path, 2)) {
        ("PUT", Some(id)) => sqlx::query_as("SELECT agent_workspace_id,agent_workspace_name FROM todo_projects WHERE user_id=$1 AND id=$2 AND deleted_at IS NULL")
            .bind(user).bind(id).fetch_optional(&state.pool).await.map_err(db)?,
        _ => None,
    };
    let Some(wanted) = wanted else {
        data["agent_workspace_id"] = Value::Null;
        data["agent_workspace_name"] = Value::Null;
        return Ok(());
    };
    if !crate::domain::valid_agent_id(&wanted) {
        return Err(invalid(
            "agent_workspace_id",
            "Workspace Agent không hợp lệ.",
        ));
    }
    let name = match catalog_for(state, user, false).await {
        Ok(catalog) => catalog
            .workspace(&wanted)
            .map(|w| w.name.clone())
            .ok_or_else(|| {
                invalid(
                    "agent_workspace_id",
                    "Workspace không còn tồn tại trên Agent.",
                )
            })?,
        Err(_) => match stored {
            Some((Some(id), name)) if id == wanted => name.unwrap_or_default(),
            _ => return Err(unavailable("agent_workspace_id")),
        },
    };
    data["agent_workspace_id"] = Value::String(wanted);
    data["agent_workspace_name"] = Value::String(name);
    Ok(())
}

async fn enrich_task(
    state: &TodoState,
    user: Uuid,
    path: &str,
    method: &str,
    data: &mut Value,
) -> Result<(), HttpError> {
    let repo = text(data, "agent_repo_id");
    let auto = data
        .get("agent_auto")
        .and_then(Value::as_bool)
        .unwrap_or(false);
    let Some(repo) = repo else {
        data["agent_repo_id"] = Value::Null;
        data["agent_repo_name"] = Value::Null;
        // `TodoInput::validate` rejects auto without a repo.
        return Ok(());
    };
    if !crate::domain::valid_agent_id(&repo) {
        return Err(invalid("agent_repo_id", "Repository Agent không hợp lệ."));
    }
    let project = data.get("project_id").and_then(Value::as_i64);
    let Some(project) = project else {
        return Err(invalid(
            "agent_repo_id",
            "Cần chọn project trước khi chọn repository.",
        ));
    };
    let workspace: Option<Option<String>> = sqlx::query_scalar("SELECT agent_workspace_id FROM todo_projects WHERE user_id=$1 AND id=$2 AND deleted_at IS NULL")
        .bind(user).bind(project).fetch_optional(&state.pool).await.map_err(db)?;
    let workspace = workspace
        .ok_or_else(|| invalid("project_id", "Project không tồn tại."))?
        .ok_or_else(|| invalid("agent_repo_id", "Project chưa gắn workspace Agent."))?;
    let stored: Option<(Option<String>, Option<String>, bool)> = match (method, path_id(path, 1)) {
        ("PUT", Some(id)) => sqlx::query_as("SELECT agent_repo_id,agent_repo_name,agent_auto FROM todos WHERE user_id=$1 AND id=$2 AND deleted_at IS NULL")
            .bind(user).bind(id).fetch_optional(&state.pool).await.map_err(db)?,
        _ => None,
    };
    let unchanged = stored
        .as_ref()
        .is_some_and(|(id, _, _)| id.as_deref() == Some(repo.as_str()));
    let was_auto = stored.as_ref().is_some_and(|(_, _, a)| *a);
    if auto && !was_auto && !link::is_linked(&state.pool, user).await.map_err(error)? {
        return Err(invalid(
            "agent_auto",
            "Cần kết nối Agent trước khi bật tự động xử lý.",
        ));
    }
    let name = match catalog_for(state, user, false).await {
        Ok(catalog) => {
            let found = catalog
                .workspace(&workspace)
                .and_then(|w| w.repos.iter().find(|r| r.id == repo))
                .ok_or_else(|| {
                    invalid(
                        "agent_repo_id",
                        "Repository không thuộc workspace của project này.",
                    )
                })?;
            if (!unchanged || (auto && !was_auto)) && !found.has_main {
                return Err(invalid(
                    "agent_repo_id",
                    "Repository chưa có nhánh main nên Agent không thể xử lý.",
                ));
            }
            found.name.clone()
        }
        Err(_) => match stored {
            Some((Some(id), name, _)) if id == repo => name.unwrap_or_default(),
            _ => return Err(unavailable("agent_repo_id")),
        },
    };
    data["agent_repo_id"] = Value::String(repo);
    data["agent_repo_name"] = Value::String(name);
    Ok(())
}
