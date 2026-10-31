use crate::infrastructure::dictionary::Dictionary;
use axum::{Router, routing::get};
#[derive(Clone)]
pub struct ForeignState {
    pub dictionary: Dictionary,
    pub identity: identity::IdentityState,
}
pub fn foreign_routes(identity: identity::IdentityState) -> Router {
    Router::new()
        .route("/", get(super::handlers::demo))
        .route("/dictionary", get(super::handlers::lookup))
        .with_state(ForeignState {
            dictionary: Dictionary::new(),
            identity,
        })
}
