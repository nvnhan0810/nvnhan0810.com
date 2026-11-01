use crate::infrastructure::dictionary::Dictionary;
use axum::{Router, routing::get};
#[derive(Clone)]
pub struct ForeignState {
    pub dictionary: Dictionary,
    pub identity: identity::IdentityState,
    pub pool: sqlx::PgPool,
    pub transcript_queue: crate::infrastructure::redis_queue::Config,
}
/// Build routes with an existing pool, without starting background workers.
/// Application hosts should use `foreign::start` for the full module lifecycle.
pub fn foreign_routes(
    identity: identity::IdentityState,
    pool: sqlx::PgPool,
    redis: app_resources::SharedRedis,
) -> Result<Router, String> {
    let queue = crate::infrastructure::redis_queue::Config::new(redis)?;
    Ok(build_routes(identity, pool, queue))
}
pub(crate) fn build_routes(
    identity: identity::IdentityState,
    pool: sqlx::PgPool,
    transcript_queue: crate::infrastructure::redis_queue::Config,
) -> Router {
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
            transcript_queue,
        })
}
