use axum::http::{HeaderValue, Method, header};
use identity::{application::ports::CorsOriginRepository, domain::value_objects::WebOrigin};
use std::sync::Arc;
use tower_http::cors::{AllowOrigin, CorsLayer};

pub fn layer(
    repository: Arc<dyn CorsOriginRepository>,
    admin_origin: Option<HeaderValue>,
    production: bool,
) -> CorsLayer {
    CorsLayer::new()
        .allow_credentials(true)
        .allow_origin(AllowOrigin::async_predicate(
            move |origin: HeaderValue, _| {
                let repository = repository.clone();
                let admin_origin = admin_origin.clone();
                async move {
                    let Some(origin) = origin
                        .to_str()
                        .ok()
                        .and_then(|value| WebOrigin::parse(value).ok())
                    else {
                        return false;
                    };
                    if production && !origin.is_https() {
                        return false;
                    }
                    if admin_origin
                        .as_ref()
                        .is_some_and(|admin| admin.as_bytes() == origin.as_str().as_bytes())
                    {
                        return true;
                    }
                    match repository.allows_origin(origin.as_str()).await {
                        Ok(allowed) => allowed,
                        Err(_) => {
                            tracing::error!("CORS client lookup failed; origin denied");
                            false
                        }
                    }
                }
            },
        ))
        .allow_methods([
            Method::GET,
            Method::POST,
            Method::PATCH,
            Method::PUT,
            Method::DELETE,
        ])
        .allow_headers([header::AUTHORIZATION, header::CONTENT_TYPE])
    // Exact origins only; browser session endpoints also validate the client's Origin.
}

#[cfg(test)]
mod tests {
    use super::*;
    use axum::{Router, body::Body, http::Request, routing::get};
    use identity::application::{error::IdentityError, ports::PortFuture};
    use std::sync::{
        Mutex,
        atomic::{AtomicBool, AtomicUsize, Ordering},
    };
    use tower::ServiceExt;

    #[derive(Default)]
    struct Origins {
        enabled: Mutex<Vec<String>>,
        fail: AtomicBool,
        queries: AtomicUsize,
    }
    impl CorsOriginRepository for Origins {
        fn allows_origin<'a>(&'a self, origin: &'a str) -> PortFuture<'a, bool> {
            Box::pin(async move {
                self.queries.fetch_add(1, Ordering::SeqCst);
                if self.fail.load(Ordering::SeqCst) {
                    return Err(IdentityError::internal());
                }
                Ok(self
                    .enabled
                    .lock()
                    .unwrap()
                    .iter()
                    .any(|value| value == origin))
            })
        }
    }
    async fn request(app: &Router, origin: &str, preflight: bool) -> axum::response::Response {
        let mut builder = Request::builder()
            .uri("/api")
            .header(header::ORIGIN, origin);
        if preflight {
            builder = builder
                .method("OPTIONS")
                .header(header::ACCESS_CONTROL_REQUEST_METHOD, "POST")
                .header(
                    header::ACCESS_CONTROL_REQUEST_HEADERS,
                    "authorization,content-type",
                );
        }
        app.clone()
            .oneshot(builder.body(Body::empty()).unwrap())
            .await
            .unwrap()
    }
    #[tokio::test]
    async fn admin_bypasses_db_and_client_changes_apply_to_requests_and_preflights() {
        let origins = Arc::new(Origins::default());
        let app = Router::new()
            .route("/api", get(|| async { "ok" }))
            .layer(layer(
                origins.clone(),
                Some(HeaderValue::from_static("https://identity.example")),
                true,
            ));
        for preflight in [false, true] {
            let response = request(&app, "https://identity.example", preflight).await;
            assert_eq!(
                response.headers()[header::ACCESS_CONTROL_ALLOW_ORIGIN],
                "https://identity.example"
            );
            assert_eq!(
                response.headers()[header::ACCESS_CONTROL_ALLOW_CREDENTIALS],
                "true"
            );
        }
        assert_eq!(origins.queries.load(Ordering::SeqCst), 0);
        for preflight in [false, true] {
            assert!(
                !request(&app, "https://wallets.example", preflight)
                    .await
                    .headers()
                    .contains_key(header::ACCESS_CONTROL_ALLOW_ORIGIN)
            );
        }
        origins
            .enabled
            .lock()
            .unwrap()
            .push("https://wallets.example".into());
        for preflight in [false, true] {
            let response = request(&app, "https://wallets.example", preflight).await;
            assert_eq!(
                response.headers()[header::ACCESS_CONTROL_ALLOW_ORIGIN],
                "https://wallets.example"
            );
            assert!(
                response.headers()[header::VARY]
                    .to_str()
                    .unwrap()
                    .contains("origin")
            );
        }
        origins.enabled.lock().unwrap().clear();
        assert!(
            !request(&app, "https://wallets.example", true)
                .await
                .headers()
                .contains_key(header::ACCESS_CONTROL_ALLOW_ORIGIN)
        );
        origins.fail.store(true, Ordering::SeqCst);
        assert!(
            !request(&app, "https://wallets.example", false)
                .await
                .headers()
                .contains_key(header::ACCESS_CONTROL_ALLOW_ORIGIN)
        );
        assert!(
            request(&app, "https://identity.example", true)
                .await
                .headers()
                .contains_key(header::ACCESS_CONTROL_ALLOW_ORIGIN)
        );
    }
    #[tokio::test]
    async fn invalid_origins_and_production_http_are_denied() {
        let origins = Arc::new(Origins::default());
        origins
            .enabled
            .lock()
            .unwrap()
            .push("http://localhost:5174".into());
        let app = Router::new()
            .route("/api", get(|| async { "ok" }))
            .layer(layer(origins.clone(), None, true));
        for origin in [
            "null",
            "*",
            "https://app.example/path",
            "https://app.example/",
            "https://app.example?x=1",
            "http://localhost:5174",
        ] {
            assert!(
                !request(&app, origin, true)
                    .await
                    .headers()
                    .contains_key(header::ACCESS_CONTROL_ALLOW_ORIGIN)
            );
        }
        assert_eq!(origins.queries.load(Ordering::SeqCst), 0);
        let local = Router::new()
            .route("/api", get(|| async { "ok" }))
            .layer(layer(origins, None, false));
        assert_eq!(
            request(&local, "http://localhost:5174", true)
                .await
                .headers()[header::ACCESS_CONTROL_ALLOW_ORIGIN],
            "http://localhost:5174"
        );
    }
}
