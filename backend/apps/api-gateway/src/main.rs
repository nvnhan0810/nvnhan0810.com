use axum::{Json, Router, routing::get};
use serde_json::{Value, json};
use std::net::SocketAddr;

// Import các middleware từ tower-http và tower_governor
use tower_governor::{GovernorLayer, governor::GovernorConfigBuilder};
use tower_http::{compression::CompressionLayer, cors::CorsLayer, trace::TraceLayer};

#[tokio::main]
async fn main() {
    // 1. Mỗi IP được hồi 1 request / 500ms (2 request / giây), burst tối đa 5.
    let governor_conf = GovernorConfigBuilder::default()
        .per_millisecond(500)
        .burst_size(5)
        .finish()
        .expect("Rate limiter configuration must have a non-zero period and burst size");

    // 2. Khai báo router và gắn các middleware (Thứ tự thực thi từ dưới lên trên)
    let app = Router::new()
        .route("/", get(root_handler))
        .nest("/auth", identity::identity_routes())
        .nest("/order", ordering::ordering_routes())
        // Gắn các middleware tầng ứng dụng:
        .layer(GovernorLayer::new(governor_conf)) // Chống spam request
        .layer(CompressionLayer::new()) // Nén response để tiết kiệm băng thông
        .layer(CorsLayer::permissive()) // Cho phép CORS gọi từ frontend/mobile
        .layer(TraceLayer::new_for_http()); // Ghi log request/response tự động

    // Bind ra 0.0.0.0 để nhận kết nối từ port forwarding của VM
    let listener = tokio::net::TcpListener::bind("0.0.0.0:8080").await.unwrap();
    println!("API Gateway đang chạy tại http://0.0.0.0:8080");
    axum::serve(
        listener,
        app.into_make_service_with_connect_info::<SocketAddr>(),
    )
    .await
    .unwrap();
}

async fn root_handler() -> Json<Value> {
    Json(json!({ "message": "ok" }))
}
