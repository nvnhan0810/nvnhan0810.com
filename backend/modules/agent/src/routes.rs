use axum::Router;
use tower_http::services::ServeDir;

use crate::config::AgentConfig;

pub fn routes(config: AgentConfig) -> Router {
    Router::new().fallback_service(
        ServeDir::new(&config.distribution_dir).append_index_html_on_directories(false),
    )
}
#[cfg(test)]
mod tests {
    use super::*;
    use app_config::{ConfigSource, StorageConfig};
    use axum::{
        body::{to_bytes, Body},
        http::{Request, StatusCode},
    };
    use tower::ServiceExt;

    #[tokio::test]
    async fn serves_only_agent_distribution_files_from_configured_storage() {
        let root = tempfile::tempdir().unwrap();
        let source = ConfigSource::from_values([(
            "STORAGE_DIR".into(),
            root.path().to_str().unwrap().into(),
        )]);
        let storage = StorageConfig::from_source(&source).unwrap();
        let config = AgentConfig::new(&storage.public_dir);
        assert_eq!(
            config.distribution_dir,
            root.path().join("public/agent/distributions")
        );
        std::fs::create_dir_all(config.distribution_dir.join("releases/0.1.0")).unwrap();
        std::fs::create_dir_all(&storage.private_dir).unwrap();
        std::fs::write(storage.private_dir.join("secret.txt"), "private").unwrap();
        std::fs::write(storage.public_dir.join("other.txt"), "other module").unwrap();
        for (path, contents) in [
            ("install.sh", "installer"),
            ("stable.txt", "0.1.0\n"),
            ("releases/0.1.0/binary", "binary"),
            ("releases/0.1.0/binary.sha256", "checksum"),
        ] {
            std::fs::write(config.distribution_dir.join(path), contents).unwrap();
        }
        let app = Router::new().nest("/agent", routes(config));
        for (path, contents) in [
            ("install.sh", "installer"),
            ("stable.txt", "0.1.0\n"),
            ("releases/0.1.0/binary", "binary"),
            ("releases/0.1.0/binary.sha256", "checksum"),
        ] {
            let response = app
                .clone()
                .oneshot(
                    Request::builder()
                        .uri(format!("/agent/{path}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap();
            assert_eq!(response.status(), StatusCode::OK);
            assert_eq!(
                to_bytes(response.into_body(), 1024).await.unwrap().as_ref(),
                contents.as_bytes()
            );
        }
        for path in [
            "/agent/missing",
            "/agent/releases/0.1.0/",
            "/agent/../other.txt",
            "/agent/%2e%2e/other.txt",
            "/agent/../../../private/secret.txt",
        ] {
            let response = app
                .clone()
                .oneshot(Request::builder().uri(path).body(Body::empty()).unwrap())
                .await
                .unwrap();
            assert_eq!(response.status(), StatusCode::NOT_FOUND, "{path}");
        }
    }
}
