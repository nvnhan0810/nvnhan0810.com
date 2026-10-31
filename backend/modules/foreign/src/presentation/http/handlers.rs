use axum::Json;
use serde_json::{Value, json};

pub async fn demo() -> Json<Value> {
    Json(json!({ "module": "foreign", "message": "Foreign API is ready." }))
}
