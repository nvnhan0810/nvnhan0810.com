use super::routes::ForeignState;
use crate::{domain::dictionary::Entry, infrastructure::vocabulary};
use axum::{
    Json,
    extract::{Query, State},
    http::{HeaderMap, StatusCode},
};
use serde::Deserialize;
use serde_json::{Value, json};
pub type ApiError = (StatusCode, Json<Value>);
pub fn internal(_: sqlx::Error) -> ApiError {
    (
        StatusCode::INTERNAL_SERVER_ERROR,
        Json(json!({"error":"server_error"})),
    )
}
pub async fn user(state: &ForeignState, headers: &HeaderMap) -> Result<uuid::Uuid, ApiError> {
    let token = headers
        .get("authorization")
        .and_then(|h| h.to_str().ok())
        .and_then(|h| h.strip_prefix("Bearer "))
        .ok_or((
            StatusCode::UNAUTHORIZED,
            Json(json!({"error":"invalid_token"})),
        ))?;
    let profile = identity::application::handlers::profile_handler::get(&state.identity, token)
        .await
        .map_err(|e| {
            (
                StatusCode::from_u16(e.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR),
                Json(json!({"error":e.code})),
            )
        })?;
    Ok(profile.id)
}
pub async fn save(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Json(mut entry): Json<Entry>,
) -> Result<(StatusCode, Json<Value>), ApiError> {
    let owner = user(&state, &headers).await?;
    crate::application::vocabulary::validate(&mut entry)
        .map_err(|e| (StatusCode::BAD_REQUEST, Json(json!({"error":e}))))?;
    let id = vocabulary::save(&state.pool, owner, &entry)
        .await
        .map_err(internal)?;
    Ok((
        StatusCode::CREATED,
        Json(json!({"id":id,"word":entry.word})),
    ))
}
#[derive(Deserialize)]
pub struct ListQuery {
    #[serde(default)]
    pub q: String,
    pub before: Option<i64>,
}
pub async fn list(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Query(query): Query<ListQuery>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    if query.q.len() > 100 {
        return Err((
            StatusCode::BAD_REQUEST,
            Json(json!({"error":"invalid_query"})),
        ));
    }
    let mut rows = vocabulary::list(
        &state.pool,
        owner,
        &query.q,
        query.before.unwrap_or(i64::MAX),
    )
    .await
    .map_err(internal)?;
    let next = if rows.len() > 30 {
        rows.truncate(30);
        rows.last().map(|r| r.id)
    } else {
        None
    };
    Ok(Json(json!({"items":rows,"next_cursor":next})))
}
