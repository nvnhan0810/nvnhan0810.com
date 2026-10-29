use axum::{routing::get, Json, Router};
use serde_json::{json, Value};

pub fn ordering_routes() -> Router {
    Router::new().route("/", get(order_handler))
}

async fn order_handler() -> Json<Value> {
    Json(json!({ "message": "ordering route ok" }))
}
