use axum::{routing::get, Json, Router};
use serde_json::{json, Value};

#[tokio::main]
async fn main() {
    let app = Router::new()
        .route("/", get(root_handler))
        .nest("/auth", identity::identity_routes())
        .nest("/order", ordering::ordering_routes());

    // Bind ra 0.0.0.0 để nhận kết nối từ port forwarding của VM
    let listener = tokio::net::TcpListener::bind("0.0.0.0:8080").await.unwrap();
    println!("API Gateway đang chạy tại http://0.0.0.0:8080");
    axum::serve(listener, app).await.unwrap();
}

async fn root_handler() -> Json<Value> {
    Json(json!({ "message": "ok" }))
}
