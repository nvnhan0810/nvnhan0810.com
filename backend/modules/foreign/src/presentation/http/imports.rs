use super::{
    routes::ForeignState,
    vocabulary::{ApiError, internal},
};
use axum::{
    Json,
    extract::{Path, State},
    http::{HeaderMap, StatusCode},
};
use serde::Deserialize;
use serde_json::{Value, json};
use uuid::Uuid;
async fn admin(state: &ForeignState, headers: &HeaderMap) -> Result<(), ApiError> {
    let token = headers
        .get("authorization")
        .and_then(|h| h.to_str().ok())
        .and_then(|h| h.strip_prefix("Bearer "))
        .ok_or((
            StatusCode::UNAUTHORIZED,
            Json(json!({"error":"invalid_token"})),
        ))?;
    identity::application::handlers::authorization_handler::require_admin(&state.identity, token)
        .await
        .map_err(|e| {
            (
                StatusCode::from_u16(e.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR),
                Json(json!({"error":e.code})),
            )
        })?;
    Ok(())
}
#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
pub struct Start {
    #[serde(default = "default_dry")]
    dry_run: bool,
}
fn default_dry() -> bool {
    true
}
pub async fn start(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Json(input): Json<Start>,
) -> Result<(StatusCode, Json<Value>), ApiError> {
    admin(&state, &headers).await?;
    let config = app_config::ConfigSource::load().map_err(|_| {
        (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(json!({"error":"config_error"})),
        )
    })?;
    if config.optional("FOREIGN_IMPORT_ENABLED") != Some("true") {
        return Err((
            StatusCode::SERVICE_UNAVAILABLE,
            Json(json!({"error":"import_disabled"})),
        ));
    }
    let id = crate::infrastructure::importer::create_run(&state.pool, input.dry_run)
        .await
        .map_err(|e| {
            if e.as_database_error()
                .is_some_and(|e| e.is_unique_violation())
            {
                (
                    StatusCode::CONFLICT,
                    Json(json!({"error":"import_already_running"})),
                )
            } else {
                internal(e)
            }
        })?;
    let program = config
        .optional("FOREIGN_CONSOLE_PATH")
        .unwrap_or("/usr/local/bin/console");
    let mut child = match tokio::process::Command::new(program)
        .arg("foreign:import")
        .arg(if input.dry_run {
            "--dry-run"
        } else {
            "--apply"
        })
        .arg("--run-id")
        .arg(id.to_string())
        .stdin(std::process::Stdio::null())
        .kill_on_drop(false)
        .spawn()
    {
        Ok(child) => child,
        Err(_) => {
            sqlx::query("UPDATE foreign_import_runs SET status='failed',error='worker_spawn_failed',finished_at=now() WHERE id=$1").bind(id).execute(&state.pool).await.map_err(internal)?;
            return Err((
                StatusCode::INTERNAL_SERVER_ERROR,
                Json(json!({"error":"worker_spawn_failed"})),
            ));
        }
    };
    tokio::spawn(async move {
        let success = child.wait().await.is_ok_and(|s| s.success());
        if !success {
            let _=sqlx::query("UPDATE foreign_import_runs SET status='failed',error=COALESCE(error,'worker_exited'),finished_at=now() WHERE id=$1 AND status IN ('queued','running')").bind(id).execute(&state.pool).await;
        }
    });
    Ok((
        StatusCode::ACCEPTED,
        Json(
            json!({"run_id":id,"dry_run":input.dry_run,"status_url":format!("/foreign/imports/{id}")}),
        ),
    ))
}
pub async fn status(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Path(id): Path<Uuid>,
) -> Result<Json<Value>, ApiError> {
    admin(&state, &headers).await?;
    let run: Option<Value> =
        sqlx::query_scalar("SELECT to_jsonb(r) FROM foreign_import_runs r WHERE id=$1")
            .bind(id)
            .fetch_optional(&state.pool)
            .await
            .map_err(internal)?;
    run.map(Json).ok_or((
        StatusCode::NOT_FOUND,
        Json(json!({"error":"run_not_found"})),
    ))
}
