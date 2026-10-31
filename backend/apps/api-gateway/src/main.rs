use api_gateway::config;

use axum::{Json, Router, routing::get};
use serde_json::{Value, json};
use std::net::SocketAddr;

// Import các middleware từ tower-http và tower_governor
use tower_governor::{GovernorLayer, governor::GovernorConfigBuilder};
use tower_http::{compression::CompressionLayer, trace::TraceLayer};

#[tokio::main]
async fn main() {
    tracing_subscriber::fmt()
        .with_max_level(tracing::Level::INFO)
        .with_ansi(false)
        .with_writer(std::io::stderr)
        .init();
    let source = config::ConfigSource::load().expect("Configuration loading failed");
    let config =
        config::AppConfig::from_source(&source).expect("Invalid application configuration");
    let identity_state = identity::IdentityState::new(
        config.identity,
        config.database.connect_options(),
        &config.private_key_path,
        &config.public_key_path,
    )
    .await
    .expect("Identity database/key initialization failed");

    let foreign_pool = sqlx::postgres::PgPoolOptions::new()
        .max_connections(5)
        .connect_with(config.database.connect_options())
        .await
        .expect("Foreign database initialization failed");
    let cors = api_gateway::cors::layer(
        identity_state.store.clone(),
        config.admin_cors_origin,
        config.production,
    );

    // 1. Mỗi IP được hồi 1 request / 500ms (2 request / giây), burst tối đa 5.
    let governor_conf = GovernorConfigBuilder::default()
        .per_millisecond(500)
        .burst_size(5)
        .finish()
        .expect("Rate limiter configuration must have a non-zero period and burst size");

    // 2. Khai báo router và gắn các middleware (Thứ tự thực thi từ dưới lên trên)
    let app = Router::new()
        .route("/", get(root_handler))
        .nest("/auth", identity::identity_routes(identity_state.clone()))
        .nest("/order", ordering::ordering_routes())
        .nest("/foreign", foreign::foreign_routes(identity_state, foreign_pool))
        // Gắn các middleware tầng ứng dụng:
        .layer(GovernorLayer::new(governor_conf)) // Chống spam request
        .layer(CompressionLayer::new()) // Nén response để tiết kiệm băng thông
        .layer(cors) // Exact browser origins; Identity supports credentialed cookie requests.
        .layer(
            TraceLayer::new_for_http()
                // OAuth query strings contain codes/state: log the path only.
                .make_span_with(|request: &axum::http::Request<axum::body::Body>| {
                    tracing::info_span!("http_request", method = %request.method(), path = request.uri().path())
                })
                .on_response(tower_http::trace::DefaultOnResponse::new().level(tracing::Level::INFO)),
        )
        // Probes must bypass the application rate limiter.
        .route("/healthz", get(root_handler));

    // Bind ra 0.0.0.0 để nhận kết nối từ port forwarding của VM
    let listener = tokio::net::TcpListener::bind("0.0.0.0:8080").await.unwrap();
    println!("API Gateway đang chạy tại http://0.0.0.0:8080");
    axum::serve(
        listener,
        app.into_make_service_with_connect_info::<SocketAddr>(),
    )
    .with_graceful_shutdown(shutdown_signal())
    .await
    .unwrap();
}

async fn root_handler() -> Json<Value> {
    Json(json!({ "message": "ok" }))
}

async fn shutdown_signal() {
    let ctrl_c = async {
        tokio::signal::ctrl_c()
            .await
            .expect("Failed to install Ctrl+C handler");
    };
    #[cfg(unix)]
    let terminate = async {
        tokio::signal::unix::signal(tokio::signal::unix::SignalKind::terminate())
            .expect("Failed to install SIGTERM handler")
            .recv()
            .await;
    };
    #[cfg(not(unix))]
    let terminate = std::future::pending::<()>();
    tokio::select! {
        _ = ctrl_c => {},
        _ = terminate => {},
    }
}
