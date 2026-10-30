use axum::{Json, Router, routing::get};
use serde_json::{Value, json};

pub fn ordering_routes() -> Router {
    Router::new().route("/", get(order_handler))
}

async fn order_handler() -> Json<Value> {
    Json(json!({ "message": "ordering route ok" }))
}
