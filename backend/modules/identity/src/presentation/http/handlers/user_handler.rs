use super::{authentication::bearer, client_handler::Pagination};
use crate::{
    IdentityState,
    application::{error::Result, handlers::user_handler as users, models::UserAccount},
};
use axum::{
    Json,
    extract::{Path, Query, State},
    http::HeaderMap,
};
use serde::Deserialize;
use uuid::Uuid;

pub async fn list(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Query(page): Query<Pagination>,
) -> Result<Json<serde_json::Value>> {
    let users = users::list(
        &state,
        bearer(&headers)?,
        page.after,
        page.limit.unwrap_or(20),
    )
    .await?;
    Ok(Json(
        serde_json::json!({"users":users,"next_cursor":users.last().map(|u|u.id)}),
    ))
}
pub async fn get(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
) -> Result<Json<UserAccount>> {
    Ok(Json(users::get(&state, bearer(&headers)?, id).await?))
}
#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct UserUpdate {
    pub enabled: bool,
}
pub async fn update(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
    Json(input): Json<UserUpdate>,
) -> Result<Json<UserAccount>> {
    Ok(Json(
        users::set_enabled(&state, bearer(&headers)?, id, input.enabled).await?,
    ))
}
pub async fn revoke(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
) -> Result<Json<serde_json::Value>> {
    users::revoke(&state, bearer(&headers)?, id).await?;
    Ok(Json(serde_json::json!({"success":true})))
}
