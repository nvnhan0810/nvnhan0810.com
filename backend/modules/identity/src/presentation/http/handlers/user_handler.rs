use super::{authentication::access_token, client_handler::Pagination};
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
        &access_token(&state, &headers, false).await?,
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
    Ok(Json(
        users::get(&state, &access_token(&state, &headers, false).await?, id).await?,
    ))
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
        users::set_enabled(
            &state,
            &access_token(&state, &headers, true).await?,
            id,
            input.enabled,
        )
        .await?,
    ))
}
pub async fn revoke(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
) -> Result<Json<serde_json::Value>> {
    users::revoke(&state, &access_token(&state, &headers, true).await?, id).await?;
    Ok(Json(serde_json::json!({"success":true})))
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct UserCreate {
    pub email: String,
    #[serde(default)]
    pub private_app_ids: Vec<Uuid>,
}
#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct UserApps {
    pub private_app_ids: Vec<Uuid>,
}
pub async fn create(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Json(input): Json<UserCreate>,
) -> Result<Json<UserAccount>> {
    Ok(Json(
        users::create(
            &state,
            &access_token(&state, &headers, true).await?,
            &input.email,
            &input.private_app_ids,
        )
        .await?,
    ))
}
pub async fn set_apps(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
    Json(input): Json<UserApps>,
) -> Result<Json<UserAccount>> {
    Ok(Json(
        users::set_apps(
            &state,
            &access_token(&state, &headers, true).await?,
            id,
            &input.private_app_ids,
        )
        .await?,
    ))
}
