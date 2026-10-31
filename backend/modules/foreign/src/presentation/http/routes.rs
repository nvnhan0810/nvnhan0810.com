use axum::{Router, routing::get};

pub fn foreign_routes() -> Router {
    Router::new().route("/", get(super::handlers::demo))
}
