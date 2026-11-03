use super::authentication::admin;
use crate::{
    IdentityState,
    application::{
        error::Result,
        handlers::client_handler as clients,
        models::{ClientRegistration, ClientUpdate},
    },
};
use axum::{
    Json,
    extract::{Path, Query, State},
    http::{HeaderMap, StatusCode},
};
use serde::Deserialize;
use uuid::Uuid;

pub async fn create(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Json(input): Json<ClientRegistration>,
) -> Result<impl axum::response::IntoResponse> {
    admin(&state, &headers, true).await?;
    let (client, secret) = clients::register_generated(&state, input).await?;
    Ok((
        StatusCode::CREATED,
        [(axum::http::header::CACHE_CONTROL, "no-store")],
        Json(serde_json::json!({"client":client,"client_secret":secret})),
    ))
}
pub async fn rotate_id(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
    Json(input): Json<Version>,
) -> Result<Json<serde_json::Value>> {
    admin(&state, &headers, true).await?;
    Ok(Json(
        serde_json::json!({"client":clients::rotate_client_id(&state,id,input.version).await?}),
    ))
}
#[derive(Deserialize)]
pub struct Pagination {
    pub after: Option<Uuid>,
    pub limit: Option<i64>,
}
pub async fn list(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Query(page): Query<Pagination>,
) -> Result<Json<serde_json::Value>> {
    admin(&state, &headers, false).await?;
    let data = clients::list(&state, page.after, page.limit.unwrap_or(20)).await?;
    Ok(Json(
        serde_json::json!({"clients":data,"next_cursor":data.last().map(|c| c.id)}),
    ))
}
pub async fn get(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
) -> Result<Json<serde_json::Value>> {
    admin(&state, &headers, false).await?;
    Ok(Json(
        serde_json::json!({"client":clients::get(&state,id).await?}),
    ))
}
pub async fn update(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
    Json(input): Json<ClientUpdate>,
) -> Result<Json<serde_json::Value>> {
    admin(&state, &headers, true).await?;
    Ok(Json(
        serde_json::json!({"client":clients::update(&state,id,input).await?}),
    ))
}
#[derive(Deserialize)]
pub struct Version {
    pub version: i64,
}
pub async fn rotate(
    State(state): State<IdentityState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
    Json(input): Json<Version>,
) -> Result<impl axum::response::IntoResponse> {
    admin(&state, &headers, true).await?;
    let (client, secret) = clients::rotate(&state, id, input.version).await?;
    Ok((
        [(axum::http::header::CACHE_CONTROL, "no-store")],
        Json(serde_json::json!({"client":client,"client_secret":secret})),
    ))
}
