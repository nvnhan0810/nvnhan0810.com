use crate::application::error::IdentityError;
use axum::{
    Json,
    http::{HeaderValue, header},
    response::{IntoResponse, Response},
};

impl IntoResponse for IdentityError {
    fn into_response(self) -> Response {
        let mut response = (
            axum::http::StatusCode::from_u16(self.status)
                .unwrap_or(axum::http::StatusCode::INTERNAL_SERVER_ERROR),
            Json(serde_json::json!({"error":self.code})),
        )
            .into_response();
        response
            .headers_mut()
            .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
        if self.status == 401 {
            response
                .headers_mut()
                .insert(header::WWW_AUTHENTICATE, HeaderValue::from_static("Bearer"));
        }
        response
    }
}
