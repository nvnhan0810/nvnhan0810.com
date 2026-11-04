use crate::infrastructure::dictionary::Dictionary;
use axum::{Router, routing::get};
#[derive(Clone)]
pub struct ForeignState {
    pub dictionary: Dictionary,
    pub youtube: crate::infrastructure::youtube::Youtube,
    pub identity: identity::IdentityState,
    pub pool: sqlx::PgPool,
    pub transcript_queue: crate::infrastructure::postgres_queue::Config,
}
/// Build routes with an existing pool, without starting background workers.
/// Application hosts should use `foreign::start` for the full module lifecycle.
pub fn foreign_routes(
    identity: identity::IdentityState,
    pool: sqlx::PgPool,
) -> Result<Router, String> {
    let queue = crate::infrastructure::postgres_queue::Config::new()?;
    Ok(build_routes(identity, pool, queue))
}
pub(crate) fn build_routes(
    identity: identity::IdentityState,
    pool: sqlx::PgPool,
    transcript_queue: crate::infrastructure::postgres_queue::Config,
) -> Router {
    let source = app_config::ConfigSource::load();
    let key = source
        .as_ref()
        .ok()
        .and_then(|source| source.optional("GOOGLE_API_KEY"))
        .map(str::to_owned);
    let youtube = crate::infrastructure::youtube::Youtube::new(key).expect("YouTube HTTP client");
    Router::new()
        .route("/", get(super::handlers::demo))
        .route("/dictionary", get(super::handlers::lookup))
        .route(
            "/youtube/channels",
            get(super::youtube::list_channels).post(super::youtube::save_channel),
        )
        .route(
            "/youtube/channels/{id}",
            axum::routing::delete(super::youtube::remove_channel),
        )
        .route("/youtube/videos", get(super::youtube::list_videos))
        .route(
            "/youtube/videos/{id}/listening",
            axum::routing::post(super::youtube::add_listening),
        )
        .route("/pages", get(super::pages::get))
        .route("/actions", axum::routing::post(super::pages::action))
        .route("/media/preview", axum::routing::post(super::media::preview))
        .route(
            "/vocabularies",
            get(super::vocabulary::list).post(super::vocabulary::save),
        )
        .with_state(ForeignState {
            dictionary: Dictionary::new(),
            youtube,
            identity,
            pool,
            transcript_queue,
        })
}
