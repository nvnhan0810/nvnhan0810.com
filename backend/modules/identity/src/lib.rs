use axum::{Json, Router, routing::get};
use serde_json::{Value, json};

pub fn identity_routes() -> Router {
    Router::new().route("/", get(auth_handler))
}

async fn auth_handler() -> Json<Value> {
    Json(json!({ "message": "identity auth route ok" }))
}
