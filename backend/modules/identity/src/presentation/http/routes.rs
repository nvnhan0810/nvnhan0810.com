use super::handlers::user_handler as users;
use super::handlers::{client_handler as clients, sso_handler as sso, token_handler as tokens};
use crate::IdentityState;
use axum::{
    Router,
    extract::DefaultBodyLimit,
    routing::{get, post},
};

pub fn identity_routes(state: IdentityState) -> Router {
    Router::new()
        .route("/sso/authorize", get(sso::authorize))
        .route("/google/callback", get(sso::callback))
        .route("/sso/token", post(tokens::token))
        .route("/sso/revoke", post(tokens::revoke))
        .route("/me", get(tokens::profile))
        .route("/logout", post(sso::logout))
        .route(
            "/admin/sso-clients",
            get(clients::list).post(clients::create),
        )
        .route(
            "/admin/sso-clients/{id}",
            get(clients::get).patch(clients::update),
        )
        .route(
            "/admin/sso-clients/{id}/rotate-secret",
            post(clients::rotate),
        )
        .route("/admin/users", get(users::list))
        .route("/admin/users/{id}", get(users::get).patch(users::update))
        .route("/admin/users/{id}/revoke", post(users::revoke))
        .layer(DefaultBodyLimit::max(32 * 1024))
        .with_state(state)
}
