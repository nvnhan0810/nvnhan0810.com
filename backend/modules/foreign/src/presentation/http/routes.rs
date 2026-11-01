use crate::infrastructure::dictionary::Dictionary;
use axum::{Router, routing::get};
#[derive(Clone)]
pub struct ForeignState {
    pub dictionary: Dictionary,
    pub identity: identity::IdentityState,
    pub pool: sqlx::PgPool,
}
pub fn foreign_routes(identity: identity::IdentityState, pool: sqlx::PgPool) -> Router {
    Router::new()
        .route("/", get(super::handlers::demo))
        .route("/dictionary", get(super::handlers::lookup))
        .route("/pages", get(super::pages::get))
        .route("/actions", axum::routing::post(super::pages::action))
        .route("/media/preview", axum::routing::post(super::media::preview))
        .route(
            "/vocabularies",
            get(super::vocabulary::list).post(super::vocabulary::save),
        )
        .with_state(ForeignState {
            dictionary: Dictionary::new(),
            identity,
            pool,
        })
}
