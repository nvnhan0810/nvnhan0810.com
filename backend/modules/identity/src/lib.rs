use axum::{routing::get, Json, Router};
use serde_json::{json, Value};

pub fn identity_routes() -> Router {
    Router::new().route("/", get(auth_handler))
}

async fn auth_handler() -> Json<Value> {
    Json(json!({ "message": "identity auth route ok" }))
}
