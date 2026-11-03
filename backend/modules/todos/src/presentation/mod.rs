use crate::{
    application::{self, Error},
    infrastructure::{self, worker::PushConfig},
};
use axum::{
    Json, Router,
    extract::{Query, State},
    http::{HeaderMap, StatusCode},
    response::{
        Sse,
        sse::{Event, KeepAlive},
    },
    routing::{get, post},
};
use base64::{Engine, engine::general_purpose::URL_SAFE_NO_PAD};
use serde::Deserialize;
use serde_json::{Value, json};
use uuid::Uuid;
#[derive(Clone)]
pub struct TodoState {
    pub identity: identity::IdentityState,
    pub pool: sqlx::PgPool,
    pub push: PushConfig,
}
type HttpError = (StatusCode, Json<Value>);
fn response(e: Error) -> HttpError {
    match e {
        Error::Validation(errors) => (
            StatusCode::UNPROCESSABLE_ENTITY,
            Json(json!({"error":"validation_error","errors":errors})),
        ),
        Error::NotFound => (StatusCode::NOT_FOUND, Json(json!({"error":"not_found"}))),
        Error::Db(error) => {
            if error
                .as_database_error()
                .is_some_and(|e| e.is_unique_violation())
            {
                return (
                    StatusCode::UNPROCESSABLE_ENTITY,
                    Json(
                        json!({"error":"validation_error","errors":{"git_repo_url":"Repository đã được dùng cho project khác."}}),
                    ),
                );
            }
            tracing::error!(%error,"Todos database operation failed");
            (
                StatusCode::INTERNAL_SERVER_ERROR,
                Json(json!({"error":"server_error"})),
            )
        }
    }
}
async fn owner(
    state: &TodoState,
    headers: &HeaderMap,
) -> Result<identity::application::models::UserProfile, HttpError> {
    let token = headers
        .get("authorization")
        .and_then(|v| v.to_str().ok())
        .and_then(|s| s.strip_prefix("Bearer "))
        .ok_or((
            StatusCode::UNAUTHORIZED,
            Json(json!({"error":"invalid_token"})),
        ))?;
    identity::application::handlers::profile_handler::get(&state.identity, token)
        .await
        .map_err(|e| {
            (
                StatusCode::from_u16(e.status).unwrap_or(StatusCode::UNAUTHORIZED),
                Json(json!({"error":e.code})),
            )
        })
}
#[derive(Deserialize)]
struct PageQuery {
    path: String,
}
async fn page(
    State(state): State<TodoState>,
    headers: HeaderMap,
    Query(q): Query<PageQuery>,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    if !q.path.starts_with('/') || q.path.starts_with("//") || q.path.len() > 4000 {
        return Err(response(Error::NotFound));
    }
    let mut result = application::page(&state.pool, user.id, &q.path)
        .await
        .map_err(response)?;
    result["props"]["auth"] = json!({"user":user});
    Ok(Json(result))
}
#[derive(Deserialize)]
struct Action {
    path: String,
    method: String,
    #[serde(default)]
    data: Value,
}
async fn action(
    State(state): State<TodoState>,
    headers: HeaderMap,
    Json(input): Json<Action>,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    if input.path.starts_with("/matrix/web-push/") {
        return push_action(&state, user.id, &input)
            .await
            .map(Json)
            .map_err(response);
    }
    application::action(
        &state.pool,
        user.id,
        &input.path,
        &input.method,
        &input.data,
    )
    .await
    .map(Json)
    .map_err(response)
}
async fn pomodoro(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    let mut tx = state.pool.begin().await.map_err(|e| response(e.into()))?;
    infrastructure::lock(&mut tx, user.id)
        .await
        .map_err(response)?;
    let mut s = infrastructure::pomodoro(&mut tx, user.id)
        .await
        .map_err(response)?;
    infrastructure::reconcile(&mut tx, &mut s, chrono::Utc::now().timestamp_millis())
        .await
        .map_err(response)?;
    tx.commit().await.map_err(|e| response(e.into()))?;
    Ok(Json(s.payload()))
}
async fn stream(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Sse<impl futures_util::Stream<Item = Result<Event, std::convert::Infallible>>>, HttpError>
{
    let user = owner(&state, &headers).await?;
    let started = std::time::Instant::now();
    let events = futures_util::stream::unfold(
        (state.pool, user.id, started, 0_u32),
        |(pool, owner, started, n)| async move {
            if started.elapsed().as_secs() >= 120 {
                return None;
            }
            if n > 0 {
                tokio::time::sleep(std::time::Duration::from_secs(3)).await;
            }
            let event = match infrastructure::snapshot(&pool, owner).await {
                Ok(data) => Event::default().event("matrix").data(data.to_string()),
                Err(error) => {
                    tracing::error!(?error, "Todos stream failed");
                    Event::default().event("retry").data("{}")
                }
            };
            Some((Ok(event), (pool, owner, started, n + 1)))
        },
    );
    Ok(Sse::new(events).keep_alive(KeepAlive::default()))
}
async fn public_key(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    owner(&state, &headers).await?;
    Ok(Json(
        json!({"publicKey":state.push.public_key,"configured":state.push.configured()}),
    ))
}
async fn push_status(
    State(state): State<TodoState>,
    headers: HeaderMap,
) -> Result<Json<Value>, HttpError> {
    let user = owner(&state, &headers).await?;
    let rows:Vec<Value>=sqlx::query_scalar("SELECT jsonb_build_object('id',id,'is_active',is_active,'failed_sent',failed_sent,'last_failed_reason',last_failed_reason,'user_agent',user_agent) FROM web_push_subscriptions WHERE user_id=$1 ORDER BY id").bind(user.id).fetch_all(&state.pool).await.map_err(|e|response(e.into()))?;
    Ok(Json(
        json!({"configured":state.push.configured(),"subscriptions":rows}),
    ))
}
pub fn valid_endpoint(raw: &str) -> bool {
    url::Url::parse(raw).is_ok_and(|u| {
        u.scheme() == "https"
            && u.username().is_empty()
            && u.password().is_none()
            && u.port_or_known_default() == Some(443)
            && u.fragment().is_none()
            && u.host_str().is_some_and(|h| {
                h == "fcm.googleapis.com"
                    || h == "updates.push.services.mozilla.com"
                    || h == "web.push.apple.com"
                    || h.ends_with(".push.apple.com")
            })
    })
}
async fn push_action(state: &TodoState, user: Uuid, input: &Action) -> application::Result<Value> {
    let d = &input.data;
    if input.path == "/matrix/web-push/presence" {
        sqlx::query("UPDATE web_push_subscriptions SET last_focused_at=now() WHERE user_id=$1 AND endpoint=$2").bind(user).bind(d["endpoint"].as_str().unwrap_or("")).execute(&state.pool).await?;
        return Ok(json!({"ok":true}));
    }
    if input.path != "/matrix/web-push/subscribe" {
        return Err(Error::NotFound);
    }
    let subscription = d.get("subscription").unwrap_or(d);
    let endpoint = subscription["endpoint"]
        .as_str()
        .filter(|s| s.len() <= 4096 && valid_endpoint(s))
        .ok_or_else(|| Error::field("endpoint", "Push endpoint không hợp lệ."))?;
    if input.method == "DELETE" {
        sqlx::query("DELETE FROM web_push_subscriptions WHERE user_id=$1 AND endpoint=$2")
            .bind(user)
            .bind(endpoint)
            .execute(&state.pool)
            .await?;
        return Ok(json!({"ok":true}));
    }
    if input.method != "POST" {
        return Err(Error::NotFound);
    }
    let public = subscription["keys"]["p256dh"].as_str().unwrap_or("");
    let auth = subscription["keys"]["auth"].as_str().unwrap_or("");
    if !URL_SAFE_NO_PAD.decode(public).is_ok_and(|v| v.len() == 65)
        || !URL_SAFE_NO_PAD.decode(auth).is_ok_and(|v| v.len() == 16)
    {
        return Err(Error::field("keys", "Khóa Web Push không hợp lệ."));
    }
    sqlx::query("INSERT INTO web_push_subscriptions(user_id,endpoint,public_key,auth_token,user_agent) VALUES($1,$2,$3,$4,$5) ON CONFLICT(user_id,endpoint) DO UPDATE SET public_key=EXCLUDED.public_key,auth_token=EXCLUDED.auth_token,is_active=true,failed_sent=0,last_failed_reason=NULL,updated_at=now()").bind(user).bind(endpoint).bind(public).bind(auth).bind(d["userAgent"].as_str().unwrap_or("").chars().take(500).collect::<String>()).execute(&state.pool).await?;
    Ok(json!({"ok":true}))
}
pub fn routes(identity: identity::IdentityState, pool: sqlx::PgPool, push: PushConfig) -> Router {
    Router::new()
        .route("/pages", get(page))
        .route("/actions", post(action))
        .route("/pomodoro", get(pomodoro))
        .route("/matrix/stream", get(stream))
        .route("/web-push/public-key", get(public_key))
        .route("/web-push/status", get(push_status))
        .with_state(TodoState {
            identity,
            pool,
            push,
        })
}
