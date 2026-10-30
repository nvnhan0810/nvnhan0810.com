// Tests require an empty temporary database via IDENTITY_TEST_DATABASE_URL.
// They never read DATABASE_URL or production configuration.
use axum::{
    body::Body,
    http::{Request, StatusCode},
};
use chrono::{Duration, Utc};
use identity::{
    IdentityState,
    application::{
        config::IdentityConfig,
        handlers::{client_handler, sso_handler, token_handler},
        models::{AuthorizeRequest, ClientInput, ClientUpdate, GoogleIdentity, TokenRequest},
        ports::{GoogleIdentityProvider, PortFuture},
    },
    infrastructure::{crypto, postgres::IdentityStore},
};
use sqlx::{PgPool, postgres::PgPoolOptions};
use std::sync::{
    Arc,
    atomic::{AtomicUsize, Ordering},
};
use tower::ServiceExt;
use uuid::Uuid;

struct FakeGoogle(AtomicUsize);
impl GoogleIdentityProvider for FakeGoogle {
    fn authenticate<'a>(
        &'a self,
        _: &'a str,
        _: &'a str,
        _: &'a str,
    ) -> PortFuture<'a, GoogleIdentity> {
        self.0.fetch_add(1, Ordering::SeqCst);
        Box::pin(async {
            Ok(GoogleIdentity {
                subject: "google-user-1".into(),
                email: "admin@example.com".into(),
                name: "Admin".into(),
                avatar: None,
            })
        })
    }
}
struct RootGoogle;
impl GoogleIdentityProvider for RootGoogle {
    fn authenticate<'a>(
        &'a self,
        _: &'a str,
        _: &'a str,
        _: &'a str,
    ) -> PortFuture<'a, GoogleIdentity> {
        Box::pin(async {
            Ok(GoogleIdentity {
                subject: "system-root-google-subject".into(),
                email: "nguyenvannhan0810@gmail.com".into(),
                name: "System Root".into(),
                avatar: None,
            })
        })
    }
}
async fn setup() -> (IdentityState, Arc<FakeGoogle>, PgPool) {
    let url = std::env::var("IDENTITY_TEST_DATABASE_URL")
        .expect("Set IDENTITY_TEST_DATABASE_URL to a disposable empty database");
    let pool = PgPoolOptions::new()
        .max_connections(10)
        .connect(&url)
        .await
        .unwrap();
    sqlx::migrate!("./migrations").run(&pool).await.unwrap();
    let google = Arc::new(FakeGoogle(AtomicUsize::new(0)));
    let config = IdentityConfig {
        app_url: "https://sso.example".into(),
        google_client_id: "google-client".into(),
        google_client_secret: "fixture".into(),
        key_id: "test-key".into(),
        access_ttl: 900,
        refresh_ttl: 86400,
    };
    let crypto = Arc::new(
        crypto::JwtCrypto::new(
            &config,
            include_bytes!("fixtures/private.pem"),
            include_bytes!("fixtures/public.pem"),
        )
        .unwrap(),
    );
    (
        IdentityState {
            config: Arc::new(config),
            store: Arc::new(IdentityStore(pool.clone())),
            google: google.clone(),
            crypto,
        },
        google,
        pool,
    )
}
fn input(id: &str) -> ClientInput {
    ClientInput {
        client_id: id.into(),
        name: id.into(),
        domain: None,
        client_type: "public".into(),
        redirect_uris: vec![format!("https://{id}.example/callback")],
        scopes: vec![
            "profile".into(),
            "wallets:read".into(),
            "identity.clients.manage".into(),
        ],
        enabled: true,
    }
}
fn intent(id: &str) -> AuthorizeRequest {
    AuthorizeRequest {
        response_type: "code".into(),
        client_id: id.into(),
        redirect_uri: format!("https://{id}.example/callback"),
        scope: "wallets:read".into(),
        state: "app-state".into(),
        code_challenge: crypto::hash(&"a".repeat(43)),
        code_challenge_method: "S256".into(),
    }
}
fn exchange_request(code: String, redirect: String) -> TokenRequest {
    TokenRequest {
        grant_type: "authorization_code".into(),
        client_id: None,
        client_secret: None,
        code: Some(code),
        redirect_uri: Some(redirect),
        code_verifier: Some("a".repeat(43)),
        refresh_token: None,
    }
}
fn refresh_request(refresh: String) -> TokenRequest {
    TokenRequest {
        grant_type: "refresh_token".into(),
        client_id: None,
        client_secret: None,
        code: None,
        redirect_uri: None,
        code_verifier: None,
        refresh_token: Some(refresh),
    }
}
fn code(location: &str) -> String {
    url::Url::parse(location)
        .unwrap()
        .query_pairs()
        .find(|(k, _)| k == "code")
        .unwrap()
        .1
        .into_owned()
}
async fn active_grants(pool: &PgPool) -> i64 {
    sqlx::query_scalar("SELECT count(*) FROM identity_grants WHERE revoked_at IS NULL")
        .fetch_one(pool)
        .await
        .unwrap()
}

#[tokio::test]
#[ignore = "requires IDENTITY_TEST_DATABASE_URL pointing to a disposable PostgreSQL database"]
async fn oauth_end_to_end_and_replay_protection() {
    let (state, google, pool) = setup().await;
    let suffix = Uuid::new_v4().simple().to_string();
    let app_a = format!("a-{suffix}");
    let app_b = format!("b-{suffix}");
    let (client_a, _) = client_handler::register(&state, input(&app_a))
        .await
        .unwrap();
    let (client_b, _) = client_handler::register(&state, input(&app_b))
        .await
        .unwrap();
    assert!(
        !serde_json::to_string(&client_a)
            .unwrap()
            .contains("secret_hash")
    );
    assert!(
        client_handler::register(&state, input(&app_a))
            .await
            .is_err()
    );

    // Wrong callback is rejected before issuing any Google transaction.
    let mut invalid = intent(&app_a);
    invalid.redirect_uri = "https://attacker.example/cb".into();
    assert!(sso_handler::authorize(&state, invalid, None).await.is_err());
    let (location, cookie) = sso_handler::authorize(&state, intent(&app_a), None)
        .await
        .unwrap();
    let google_state = url::Url::parse(&location)
        .unwrap()
        .query_pairs()
        .find(|(k, _)| k == "state")
        .unwrap()
        .1
        .into_owned();
    let browser = cookie
        .unwrap()
        .split(';')
        .next()
        .unwrap()
        .split_once('=')
        .unwrap()
        .1
        .to_owned();
    assert!(
        sso_handler::google_callback(&state, "google-code", &google_state, "wrong-browser")
            .await
            .is_err()
    );
    assert_eq!(google.0.load(Ordering::SeqCst), 0);
    let (callback, login_cookie) =
        sso_handler::google_callback(&state, "google-code", &google_state, &browser)
            .await
            .unwrap();
    assert!(
        sso_handler::google_callback(&state, "google-code", &google_state, &browser)
            .await
            .is_err()
    );
    let login = login_cookie
        .split(';')
        .next()
        .unwrap()
        .split_once('=')
        .unwrap()
        .1
        .to_owned();
    let a_code = code(&callback);
    let mut wrong_pkce = exchange_request(a_code.clone(), intent(&app_a).redirect_uri.clone());
    wrong_pkce.code_verifier = Some("b".repeat(43));
    assert!(
        token_handler::exchange(&state, &client_a, wrong_pkce)
            .await
            .is_err()
    );
    let tokens = token_handler::exchange(
        &state,
        &client_a,
        exchange_request(a_code.clone(), intent(&app_a).redirect_uri.clone()),
    )
    .await
    .unwrap();
    assert!(
        token_handler::exchange(
            &state,
            &client_a,
            exchange_request(a_code, intent(&app_a).redirect_uri)
        )
        .await
        .is_err()
    );
    let claims = token_handler::verify_access(&state, &tokens.access_token).unwrap();
    assert_eq!(claims.client_id, app_a);
    assert_eq!(claims.aud, state.config.app_url);
    let mut wrong_audience = claims.clone();
    wrong_audience.aud = "https://other.example".into();
    assert!(
        token_handler::verify_access(&state, &state.crypto.sign(&wrong_audience).unwrap()).is_err()
    );
    assert!(token_handler::verify_access(&state, &format!("{}x", tokens.access_token)).is_err());

    // B reuses central browser authentication, not A's token.
    let b_intent = intent(&app_b);
    let (b_callback, b_cookie) = sso_handler::authorize(&state, b_intent.clone(), Some(&login))
        .await
        .unwrap();
    assert!(b_cookie.is_none());
    assert_eq!(google.0.load(Ordering::SeqCst), 1);
    let b_code = code(&b_callback);
    let (one, two) = tokio::join!(
        token_handler::exchange(
            &state,
            &client_b,
            exchange_request(b_code.clone(), b_intent.redirect_uri.clone())
        ),
        token_handler::exchange(
            &state,
            &client_b,
            exchange_request(b_code, b_intent.redirect_uri.clone())
        )
    );
    assert_eq!(usize::from(one.is_ok()) + usize::from(two.is_ok()), 1);

    // One audience supports multiple modules/profile, but scope still gates admin.
    let mut shared_intent = intent(&app_b);
    shared_intent.scope = "wallets:read profile".into();
    let (shared_cb, _) = sso_handler::authorize(&state, shared_intent.clone(), Some(&login))
        .await
        .unwrap();
    let shared_tokens = token_handler::exchange(
        &state,
        &client_b,
        exchange_request(code(&shared_cb), shared_intent.redirect_uri),
    )
    .await
    .unwrap();
    assert_eq!(
        token_handler::verify_access(&state, &shared_tokens.access_token)
            .unwrap()
            .aud,
        state.config.app_url
    );
    assert!(
        identity::application::handlers::profile_handler::get(&state, &shared_tokens.access_token)
            .await
            .is_ok()
    );
    assert!(
        identity::application::handlers::authorization_handler::require_admin(
            &state,
            &shared_tokens.access_token
        )
        .await
        .is_err()
    );
    assert!(
        serde_json::to_value(&client_b)
            .unwrap()
            .get("audiences")
            .is_none()
    );
    let mut obsolete = serde_json::to_value(intent(&app_b)).unwrap();
    obsolete["resource"] = serde_json::json!("https://other.example");
    assert!(serde_json::from_value::<AuthorizeRequest>(obsolete).is_err());

    // Rotation and replay commit revocation, including the successor token.
    let successor = token_handler::refresh(
        &state,
        &client_a,
        refresh_request(tokens.refresh_token.clone()),
    )
    .await
    .unwrap();
    assert!(
        token_handler::refresh(&state, &client_a, refresh_request(tokens.refresh_token))
            .await
            .is_err()
    );
    assert!(
        token_handler::refresh(&state, &client_a, refresh_request(successor.refresh_token))
            .await
            .is_err()
    );

    // Concurrent refresh must never create two valid successors.
    let (cb, _) = sso_handler::authorize(&state, intent(&app_a), Some(&login))
        .await
        .unwrap();
    let fresh = token_handler::exchange(
        &state,
        &client_a,
        exchange_request(code(&cb), intent(&app_a).redirect_uri),
    )
    .await
    .unwrap();
    let (one, two) = tokio::join!(
        token_handler::refresh(
            &state,
            &client_a,
            refresh_request(fresh.refresh_token.clone())
        ),
        token_handler::refresh(&state, &client_a, refresh_request(fresh.refresh_token))
    );
    assert_eq!(usize::from(one.is_ok()) + usize::from(two.is_ok()), 1);
    let winner = one.or(two).unwrap();
    assert!(
        token_handler::refresh(&state, &client_a, refresh_request(winner.refresh_token))
            .await
            .is_err()
    );

    // Expired code and wrong client are rejected without consuming a valid code.
    let (cb, _) = sso_handler::authorize(&state, intent(&app_a), Some(&login))
        .await
        .unwrap();
    let pending = code(&cb);
    assert!(
        token_handler::exchange(
            &state,
            &client_b,
            exchange_request(pending.clone(), intent(&app_a).redirect_uri.clone())
        )
        .await
        .is_err()
    );
    sqlx::query("UPDATE identity_authorization_codes SET expires_at=now()-interval '1 second' WHERE code_hash=$1")
        .bind(crypto::hash(&pending)).execute(&pool).await.unwrap();
    assert!(
        token_handler::exchange(
            &state,
            &client_a,
            exchange_request(pending, intent(&app_a).redirect_uri)
        )
        .await
        .is_err()
    );

    // Resource verifier enforces expiry and issuer as well as audience.
    let mut expired = claims.clone();
    expired.exp = Utc::now().timestamp() - 60;
    assert!(token_handler::verify_access(&state, &state.crypto.sign(&expired).unwrap()).is_err());
    expired.exp = Utc::now().timestamp() + 60;
    expired.iss = "https://attacker.example".into();
    assert!(token_handler::verify_access(&state, &state.crypto.sign(&expired).unwrap()).is_err());

    // Confidential client credentials never appear in read DTOs. Rotation is versioned.
    let confidential_id = format!("c-{suffix}");
    let mut confidential_input = input(&confidential_id);
    confidential_input.client_type = "confidential".into();
    let (confidential, secret) = client_handler::register(&state, confidential_input)
        .await
        .unwrap();
    let secret = secret.unwrap();
    assert!(
        token_handler::authenticate_client(&state, &confidential_id, None)
            .await
            .is_err()
    );
    assert!(
        token_handler::authenticate_client(&state, &confidential_id, Some("wrong"))
            .await
            .is_err()
    );
    assert!(
        token_handler::authenticate_client(&state, &confidential_id, Some(&secret))
            .await
            .is_ok()
    );
    let (rotated, new_secret) =
        client_handler::rotate(&state, confidential.id, confidential.version)
            .await
            .unwrap();
    assert!(
        token_handler::authenticate_client(&state, &confidential_id, Some(&secret))
            .await
            .is_err()
    );
    assert!(
        token_handler::authenticate_client(&state, &confidential_id, Some(&new_secret))
            .await
            .is_ok()
    );
    assert!(
        client_handler::rotate(&state, confidential.id, confidential.version)
            .await
            .is_err()
    );

    // HTTP form exchange + client_secret_basic, no-store headers and revocation.
    let (cb, _) = sso_handler::authorize(&state, intent(&confidential_id), Some(&login))
        .await
        .unwrap();
    let form = url::form_urlencoded::Serializer::new(String::new())
        .append_pair("grant_type", "authorization_code")
        .append_pair("code", &code(&cb))
        .append_pair("redirect_uri", &intent(&confidential_id).redirect_uri)
        .append_pair("code_verifier", &"a".repeat(43))
        .finish();
    use base64::Engine;
    let basic =
        base64::engine::general_purpose::STANDARD.encode(format!("{confidential_id}:{new_secret}"));
    let router = identity::identity_routes(state.clone());
    let response = router
        .clone()
        .oneshot(
            Request::builder()
                .method("POST")
                .uri("/sso/token")
                .header("Authorization", format!("Basic {basic}"))
                .header("Content-Type", "application/x-www-form-urlencoded")
                .body(Body::from(form))
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(response.status(), StatusCode::OK);
    assert_eq!(response.headers()["cache-control"], "no-store");
    let body = axum::body::to_bytes(response.into_body(), 32768)
        .await
        .unwrap();
    let issued: identity::application::models::TokenResponse =
        serde_json::from_slice(&body).unwrap();
    token_handler::revoke(&state, &client_b, &issued.refresh_token)
        .await
        .unwrap();
    let next = token_handler::refresh(&state, &rotated, refresh_request(issued.refresh_token))
        .await
        .unwrap();
    token_handler::revoke(&state, &rotated, &next.refresh_token)
        .await
        .unwrap();
    assert!(
        token_handler::refresh(&state, &rotated, refresh_request(next.refresh_token))
            .await
            .is_err()
    );

    // A non-admin still cannot obtain an admin scope even with an approved client.
    let mut admin_attempt = intent(&app_a);
    admin_attempt.scope = "identity.clients.manage".into();
    assert!(
        sso_handler::authorize(&state, admin_attempt, Some(&login))
            .await
            .is_err()
    );

    // New module scopes are configured in DB; all tokens use the same APP_URL.
    let dynamic_id = format!("dynamic-{suffix}");
    let mut dynamic_input = input(&dynamic_id);
    dynamic_input.scopes = vec!["reports:read".into()];
    let (dynamic, _) = client_handler::register(&state, dynamic_input)
        .await
        .unwrap();
    let mut dynamic_intent = intent(&dynamic_id);
    dynamic_intent.scope = "reports:read".into();
    let (cb, _) = sso_handler::authorize(&state, dynamic_intent.clone(), Some(&login))
        .await
        .unwrap();
    let dynamic_tokens = token_handler::exchange(
        &state,
        &dynamic,
        exchange_request(code(&cb), dynamic_intent.redirect_uri.clone()),
    )
    .await
    .unwrap();
    assert!(token_handler::verify_access(&state, &dynamic_tokens.access_token).is_ok());
    client_handler::update(
        &state,
        dynamic.id,
        ClientUpdate {
            version: dynamic.version,
            name: dynamic.name.clone(),
            domain: None,
            redirect_uris: dynamic.redirect_uris.clone(),
            scopes: vec!["reports:write".into()],
            enabled: true,
        },
    )
    .await
    .unwrap();
    assert!(
        sso_handler::authorize(&state, dynamic_intent, Some(&login))
            .await
            .is_err()
    );
    assert!(
        token_handler::refresh(
            &state,
            &dynamic,
            refresh_request(dynamic_tokens.refresh_token)
        )
        .await
        .is_err()
    );

    // Admin API enforces audience and rights; cookie is not API authentication.
    let router = identity::identity_routes(state.clone());
    let denied = router
        .clone()
        .oneshot(
            Request::builder()
                .uri("/admin/sso-clients")
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(denied.status(), StatusCode::UNAUTHORIZED);
    let mut admin_intent = intent(&app_a);
    admin_intent.scope = "profile".into();
    let (cb, _) = sso_handler::authorize(&state, admin_intent.clone(), Some(&login))
        .await
        .unwrap();
    let admin_token = token_handler::exchange(
        &state,
        &client_a,
        exchange_request(code(&cb), admin_intent.redirect_uri),
    )
    .await
    .unwrap();
    let allowed = router
        .clone()
        .oneshot(
            Request::builder()
                .uri("/admin/sso-clients")
                .header(
                    "Authorization",
                    format!("Bearer {}", admin_token.access_token),
                )
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(allowed.status(), StatusCode::FORBIDDEN);

    let update = ClientUpdate {
        version: client_a.version,
        name: "Disabled".into(),
        domain: None,
        redirect_uris: client_a.redirect_uris.clone(),
        scopes: client_a.scopes.clone(),
        enabled: false,
    };
    client_handler::update(&state, client_a.id, update)
        .await
        .unwrap();
    assert!(
        sso_handler::authorize(&state, intent(&app_a), Some(&login))
            .await
            .is_err()
    );
    assert!(
        token_handler::authenticate_client(&state, &app_a, None)
            .await
            .is_err()
    );
    assert!(active_grants(&pool).await >= 1);
    // Even a previously authenticated Client snapshot cannot issue tokens after disable.
    assert!(
        token_handler::refresh(
            &state,
            &client_a,
            refresh_request(admin_token.refresh_token)
        )
        .await
        .is_err()
    );

    // Expired central login no longer bypasses Google.
    sqlx::query("UPDATE identity_browser_logins SET expires_at=$1 WHERE token_hash=$2")
        .bind(Utc::now() - Duration::seconds(1))
        .bind(crypto::hash(&login))
        .execute(&pool)
        .await
        .unwrap();
    assert!(
        sso_handler::authorize(&state, intent(&app_b), Some(&login))
            .await
            .unwrap()
            .1
            .is_some()
    );
    // Root is the sole administrator; ordinary verified Google users can log in.
    let mut root_state = state.clone();
    root_state.google = Arc::new(RootGoogle);
    let root_client_id = format!("root-{suffix}");
    let (root_client, _) = client_handler::register(&root_state, input(&root_client_id))
        .await
        .unwrap();
    let mut root_intent = intent(&root_client_id);
    root_intent.scope = "profile".into(); // Root does not need the management scope.
    let (google_url, cookie) = sso_handler::authorize(&root_state, root_intent.clone(), None)
        .await
        .unwrap();
    let root_state_id = url::Url::parse(&google_url)
        .unwrap()
        .query_pairs()
        .find(|(k, _)| k == "state")
        .unwrap()
        .1
        .into_owned();
    let root_browser = cookie
        .unwrap()
        .split(';')
        .next()
        .unwrap()
        .split_once('=')
        .unwrap()
        .1
        .to_owned();
    let (cb, _) =
        sso_handler::google_callback(&root_state, "root-code", &root_state_id, &root_browser)
            .await
            .unwrap();
    let root_tokens = token_handler::exchange(
        &root_state,
        &root_client,
        exchange_request(code(&cb), root_intent.redirect_uri),
    )
    .await
    .unwrap();
    let root_claims = token_handler::verify_access(&root_state, &root_tokens.access_token).unwrap();
    assert!(root_claims.is_system_root);
    let users = identity::application::handlers::user_handler::list(
        &root_state,
        &root_tokens.access_token,
        None,
        100,
    )
    .await
    .unwrap();
    assert!(
        users
            .iter()
            .any(|u| u.id == root_claims.sub && u.is_system_root)
    );
    assert!(
        identity::application::handlers::user_handler::set_enabled(
            &root_state,
            &root_tokens.access_token,
            root_claims.sub,
            false
        )
        .await
        .is_err()
    );
    assert!(
        sqlx::query("UPDATE identity_users SET enabled=FALSE WHERE id=$1")
            .bind(root_claims.sub)
            .execute(&pool)
            .await
            .is_err()
    );
    let target = identity::application::handlers::user_handler::set_enabled(
        &root_state,
        &root_tokens.access_token,
        claims.sub,
        false,
    )
    .await
    .unwrap();
    assert!(!target.enabled);
    assert!(state.store.profile(claims.sub).await.is_err());
    identity::application::handlers::user_handler::set_enabled(
        &root_state,
        &root_tokens.access_token,
        claims.sub,
        true,
    )
    .await
    .unwrap();
    // Re-enabling cannot resurrect revoked grants.
    assert!(
        !state
            .store
            .active_grant(claims.grant_id, claims.sub, &claims.client_id)
            .await
            .unwrap()
    );
    let refreshed_root = token_handler::refresh(
        &root_state,
        &root_client,
        refresh_request(root_tokens.refresh_token),
    )
    .await
    .unwrap();
    assert!(
        token_handler::verify_access(&root_state, &refreshed_root.access_token)
            .unwrap()
            .is_system_root
    );

    pool.close().await;
}
