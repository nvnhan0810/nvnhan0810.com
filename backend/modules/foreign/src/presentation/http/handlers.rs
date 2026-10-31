use axum::Json;
use serde_json::{Value, json};

pub async fn demo() -> Json<Value> {
    Json(json!({ "module": "foreign", "message": "Foreign API is ready." }))
}

use crate::{
    domain::dictionary::{Entry, normalize_word},
    infrastructure::dictionary::{Dictionary, LookupError},
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
    State(dictionary): State<Dictionary>,
    Query(query): Query<LookupQuery>,
) -> Result<Json<Vec<Entry>>, (StatusCode, Json<Value>)> {
    let word = normalize_word(&query.q).ok_or((
        StatusCode::BAD_REQUEST,
        Json(json!({"error":"invalid_word"})),
    ))?;
    dictionary.lookup(&word).await.map(Json).map_err(|error| {
        let (status, code) = match error {
            LookupError::NotFound => (StatusCode::NOT_FOUND, "word_not_found"),
            LookupError::Unavailable => (StatusCode::BAD_GATEWAY, "dictionary_unavailable"),
            LookupError::Busy => (StatusCode::SERVICE_UNAVAILABLE, "dictionary_busy"),
        };
        (status, Json(json!({"error":code})))
    })
}
