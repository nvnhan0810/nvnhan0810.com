use crate::infrastructure::dictionary::Dictionary;
use axum::{Router, routing::get};
pub fn foreign_routes() -> Router {
    Router::new()
        .route("/", get(super::handlers::demo))
        .route("/dictionary", get(super::handlers::lookup))
        .with_state(Dictionary::new())
}
