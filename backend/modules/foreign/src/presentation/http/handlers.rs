use axum::Json;
use serde_json::{Value, json};

pub async fn demo() -> Json<Value> {
    Json(json!({ "module": "foreign", "message": "Foreign API is ready." }))
}

use crate::{
    domain::dictionary::{Entry, normalize_word},
    infrastructure::dictionary::LookupError,
};
use axum::{
    extract::{Query, State},
    http::StatusCode,
};
use serde::Deserialize;
#[derive(Deserialize)]
pub struct LookupQuery {
    pub q: String,
}
pub async fn lookup(
    State(state): State<super::routes::ForeignState>,
    headers: axum::http::HeaderMap,
    Query(query): Query<LookupQuery>,
) -> Result<Json<Vec<Entry>>, (StatusCode, Json<Value>)> {
    let token = headers
        .get(axum::http::header::AUTHORIZATION)
        .and_then(|h| h.to_str().ok())
        .and_then(|h| h.strip_prefix("Bearer "))
        .ok_or((
            StatusCode::UNAUTHORIZED,
            Json(json!({"error":"invalid_token"})),
        ))?;
    identity::application::handlers::profile_handler::get(&state.identity, token)
        .await
        .map_err(|error| {
            (
                StatusCode::from_u16(error.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR),
                Json(json!({"error":"invalid_token"})),
            )
        })?;
    let word = normalize_word(&query.q).ok_or((
        StatusCode::BAD_REQUEST,
        Json(json!({"error":"invalid_word"})),
    ))?;
    state
        .dictionary
        .lookup(&word)
        .await
        .map(Json)
        .map_err(|error| {
            let (status, code) = match error {
                LookupError::NotFound => (StatusCode::NOT_FOUND, "word_not_found"),
                LookupError::Unavailable => (StatusCode::BAD_GATEWAY, "dictionary_unavailable"),
                LookupError::Busy => (StatusCode::SERVICE_UNAVAILABLE, "dictionary_busy"),
            };
            (status, Json(json!({"error":code})))
        })
}
