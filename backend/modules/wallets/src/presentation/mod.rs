use crate::{application, infrastructure};
use axum::{
    Json, Router,
    extract::{Query, State},
    http::{HeaderMap, StatusCode},
    routing::{get, post},
};
use serde::Deserialize;
use serde_json::{Value, json};
#[derive(Clone)]
pub struct WalletsState {
    pub identity: identity::IdentityState,
    pub pool: sqlx::PgPool,
}
type Error = (StatusCode, Json<Value>);
fn bad(e: impl ToString) -> Error {
    (
        StatusCode::UNPROCESSABLE_ENTITY,
        Json(json!({"error":e.to_string(),"errors":{"request":e.to_string()}})),
    )
}
fn internal(e: sqlx::Error) -> Error {
    tracing::error!(error=%e,"Wallets database operation failed");
    (
        StatusCode::INTERNAL_SERVER_ERROR,
        Json(json!({"error":"server_error"})),
    )
}
async fn user(
    state: &WalletsState,
    headers: &HeaderMap,
) -> Result<identity::application::models::UserProfile, Error> {
    let token = headers
        .get("authorization")
        .and_then(|h| h.to_str().ok())
        .and_then(|h| h.strip_prefix("Bearer "))
        .ok_or((
            StatusCode::UNAUTHORIZED,
            Json(json!({"error":"invalid_token"})),
        ))?;
    identity::application::handlers::profile_handler::get(&state.identity, token)
        .await
        .map_err(|e| {
            (
                StatusCode::from_u16(e.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR),
                Json(json!({"error":e.code})),
            )
        })
}
#[derive(Deserialize)]
struct PageQuery {
    path: String,
}
async fn page(
    State(state): State<WalletsState>,
    headers: HeaderMap,
    Query(q): Query<PageQuery>,
) -> Result<Json<Value>, Error> {
    let profile = user(&state, &headers).await?;
    let url = reqwest_url(&q.path)?;
    let path = url.path();
    let query = url
        .query_pairs()
        .map(|(k, v)| (k.into_owned(), json!(v.into_owned())))
        .collect::<serde_json::Map<_, _>>();
    let mut tx = state.pool.begin().await.map_err(internal)?;
    let mut a = infrastructure::load(&mut tx, profile.id)
        .await
        .map_err(internal)?;
    a.normalize();
    crate::domain::lending::generate_recurring(&mut a).map_err(bad)?;
    let mut result = application::pages::page(&a, path, &Value::Object(query)).map_err(bad)?;
    result["props"]["auth"] = json!({"user":profile});
    result["props"]["errors"] = json!({});
    result["props"]["flash"] = json!({});
    infrastructure::save(&mut tx, profile.id, &a)
        .await
        .map_err(internal)?;
    tx.commit().await.map_err(internal)?;
    Ok(Json(result))
}
fn reqwest_url(path: &str) -> Result<url::Url, Error> {
    if !path.starts_with('/') || path.starts_with("//") {
        return Err(bad("Đường dẫn không hợp lệ"));
    }
    url::Url::parse(&format!("http://wallets.local{path}")).map_err(bad)
}
#[derive(Deserialize)]
struct Action {
    path: String,
    method: String,
    data: Value,
}
async fn action(
    State(state): State<WalletsState>,
    headers: HeaderMap,
    Json(d): Json<Action>,
) -> Result<Json<Value>, Error> {
    let owner = user(&state, &headers).await?.id;
    let mut tx = state.pool.begin().await.map_err(internal)?;
    let mut a = infrastructure::load(&mut tx, owner)
        .await
        .map_err(internal)?;
    a.normalize();
    let result = application::action(&mut a, &d.path, &d.method, &d.data).map_err(bad)?;
    infrastructure::save(&mut tx, owner, &a)
        .await
        .map_err(internal)?;
    tx.commit().await.map_err(internal)?;
    Ok(Json(result))
}
pub fn routes(identity: identity::IdentityState, pool: sqlx::PgPool) -> Router {
    Router::new()
        .route("/pages", get(page))
        .route("/actions", post(action))
        .with_state(WalletsState { identity, pool })
}
