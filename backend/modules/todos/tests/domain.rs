use todos::domain::{ProjectInput, TodoInput, pomodoro::State, quadrant};
use uuid::Uuid;
fn state() -> State {
    State {
        user_id: Uuid::new_v4(),
        focus_minutes: 20,
        short_break_minutes: 5,
        sessions_before_long_break: 2,
        long_break_minutes: 15,
        phase: "focus".into(),
        remaining_ms: 1200000,
        ends_at: None,
        focus_count: 0,
        active_todo_id: None,
        is_running: false,
        session_uuid: None,
        last_focused_at: None,
        client_updated_at: 0,
    }
}
#[test]
fn running_start_is_idempotent_and_pause_resume_preserves_time() {
    let mut s = state();
    s.start(1000);
    let session = s.session_uuid;
    assert_eq!(s.ends_at, Some(1201000));
    s.start(2000);
    assert_eq!(s.session_uuid, session);
    assert_eq!(s.ends_at, Some(1201000));
    s.pause(31000);
    assert_eq!(s.remaining_ms, 1170000);
    assert!(!s.is_running);
    s.start(50000);
    assert_eq!(s.ends_at, Some(1220000));
    assert_ne!(s.session_uuid, session);
}
#[test]
fn phases_count_focus_sessions_and_settings_control_durations() {
    let mut s = state();
    s.start(0);
    s.advance(1200000);
    assert_eq!(s.phase, "short_break");
    assert_eq!(s.remaining_ms, 300000);
    assert_eq!(s.focus_count, 1);
    s.advance(1500000);
    assert_eq!(s.phase, "focus");
    s.advance(2700000);
    assert_eq!(s.phase, "long_break");
    assert_eq!(s.focus_count, 0);
    assert_eq!(s.remaining_ms, 900000);
    s.pause(2700010);
    s.advance(2800000);
    assert!(!s.is_running);
    assert_eq!(s.ends_at, None);
    assert_eq!(s.phase, "focus");
    s.reset(2900000);
    assert_eq!(s.remaining_ms, 1200000);
}
#[test]
fn task_validation_rejects_missing_titles_bad_statuses_and_dates() {
    let mut d = TodoInput {
        title: "  ".into(),
        description: None,
        project_id: None,
        status: "unknown".into(),
        priority: "bad".into(),
        due_at: Some("not-a-date".into()),
        is_urgent: false,
        is_important: true,
        depends_on: None,
        agent_repo_id: None,
        agent_repo_name: None,
        agent_auto: false,
        agent_base_branch: None,
    };
    let errors = d.validate().unwrap_err();
    for field in ["title", "status", "priority", "due_at"] {
        assert!(errors.contains_key(field));
    }
    d.title = "  Task  ".into();
    d.status = "todo".into();
    d.priority = "high".into();
    d.due_at = Some("2026-11-03T20:30:00+07:00".into());
    assert!(d.validate().is_ok());
    assert_eq!(d.title, "Task");
}
#[test]
fn projects_only_expose_safe_http_urls() {
    let mut d = ProjectInput {
        name: "Work".into(),
        git_repo_url: Some("javascript:alert(1)".into()),
        domain: Some("https://user:secret@example.com".into()),
        agent_workspace_id: None,
        agent_workspace_name: None,
        identifier_prefix: None,
    };
    assert_eq!(d.validate().unwrap_err().len(), 2);
    d.git_repo_url = Some("https://github.com/user/repo".into());
    d.domain = Some("https://example.com".into());
    assert!(d.validate().is_ok());
}
#[test]
fn all_eisenhower_quadrants_are_distinct() {
    assert_eq!(quadrant(true, true), "do");
    assert_eq!(quadrant(false, true), "schedule");
    assert_eq!(quadrant(true, false), "delegate");
    assert_eq!(quadrant(false, false), "eliminate");
}
#[test]
fn push_subscription_cannot_target_private_hosts() {
    use todos::presentation::valid_endpoint;
    for endpoint in [
        "http://fcm.googleapis.com/x",
        "https://127.0.0.1/push",
        "https://fcm.googleapis.com.evil.example/x",
        "https://user:secret@fcm.googleapis.com/x",
        "https://fcm.googleapis.com:8443/x",
    ] {
        assert!(!valid_endpoint(endpoint), "{endpoint}");
    }
    assert!(valid_endpoint(
        "https://fcm.googleapis.com/fcm/send/example"
    ));
    assert!(valid_endpoint("https://web.push.apple.com/QH/example"));
}
#[test]
fn in_review_is_a_valid_status() {
    assert!(todos::domain::STATUSES.contains(&"in_review"));
    let mut d = TodoInput {
        title: "Task".into(),
        description: None,
        project_id: None,
        status: "in_review".into(),
        priority: "medium".into(),
        due_at: None,
        is_urgent: false,
        is_important: false,
        depends_on: None,
        agent_repo_id: None,
        agent_repo_name: None,
        agent_auto: false,
        agent_base_branch: None,
    };
    assert!(d.validate().is_ok());
}
#[test]
fn dependencies_are_deduped_and_reject_self_and_invalid_ids() {
    use todos::domain::{MAX_DEPENDENCIES, normalize_dependencies};
    assert_eq!(
        normalize_dependencies(Some(1), &[5, 3, 5]).unwrap(),
        vec![3, 5]
    );
    assert_eq!(
        normalize_dependencies(None, &[]).unwrap(),
        Vec::<i64>::new()
    );
    assert!(normalize_dependencies(Some(3), &[1, 3]).is_err());
    assert!(normalize_dependencies(Some(1), &[0]).is_err());
    assert!(normalize_dependencies(Some(1), &[-2]).is_err());
    let many: Vec<i64> = (1..=MAX_DEPENDENCIES as i64 + 1).collect();
    assert!(normalize_dependencies(None, &many).is_err());
}

#[test]
fn agent_catalog_matches_the_agent_contract_fixture() {
    use todos::application::agent_link::Catalog;
    let raw = include_str!("fixtures/integration/v1/catalog.v1.json");
    let catalog: Catalog = serde_json::from_str(raw).unwrap();
    let catalog = catalog.validated().expect("fixture passes validation");
    let work = catalog
        .workspace("6f1d2c3a-0000-4000-8000-000000000001")
        .expect("workspace");
    assert_eq!(work.name, "Work");
    assert!(work.repos[0].has_main);
    assert!(!work.repos[1].has_main);
    assert_eq!(work.repos[0].base_branch, "main");
    assert_eq!(work.repos[1].base_branch, "develop");
    assert_eq!(work.repos[0].full_name, "acme/api");
}
#[test]
fn agent_catalog_rejects_hostile_or_oversized_replies() {
    use todos::application::agent_link::{Catalog, CatalogRepo, CatalogWorkspace};
    let repo = CatalogRepo {
        id: "ok-1".into(),
        name: "api".into(),
        full_name: "acme/api".into(),
        base_branch: "main".into(),
        has_main: true,
    };
    let bad_id = Catalog {
        workspaces: vec![CatalogWorkspace {
            id: "../../etc".into(),
            name: "x".into(),
            repos: vec![repo.clone()],
        }],
    };
    assert!(bad_id.validated().is_none());
    let long_name = Catalog {
        workspaces: vec![CatalogWorkspace {
            id: "w1".into(),
            name: "n".repeat(256),
            repos: vec![repo],
        }],
    };
    assert!(long_name.validated().is_none());
}
#[test]
fn agent_fields_are_validated_before_they_reach_the_database() {
    use todos::domain::{ProjectInput, TodoInput};
    let base = || TodoInput {
        title: "Task".into(),
        description: None,
        project_id: Some(1),
        status: "todo".into(),
        priority: "medium".into(),
        due_at: None,
        is_urgent: false,
        is_important: false,
        depends_on: None,
        agent_repo_id: Some("3b1e0000-0000-4000-8000-000000000002".into()),
        agent_repo_name: Some("client-supplied".into()),
        agent_auto: true,
        agent_base_branch: None,
    };
    let mut ok = base();
    assert!(ok.validate().is_ok());
    // Name is a snapshot; the server overwrites it, but validation must not trust it either.
    let mut no_project = base();
    no_project.project_id = None;
    assert!(
        no_project
            .validate()
            .unwrap_err()
            .contains_key("agent_repo_id")
    );
    // No repo: the task runs in the project's Agent workspace root.
    let mut auto_without_repo = base();
    auto_without_repo.agent_repo_id = Some("   ".into());
    auto_without_repo.validate().unwrap();
    assert_eq!(auto_without_repo.agent_repo_name, None);
    let mut auto_without_project = base();
    auto_without_project.agent_repo_id = None;
    auto_without_project.project_id = None;
    assert!(
        auto_without_project
            .validate()
            .unwrap_err()
            .contains_key("agent_auto")
    );
    let mut bad_id = base();
    bad_id.agent_repo_id = Some("a/../b".into());
    assert!(bad_id.validate().unwrap_err().contains_key("agent_repo_id"));
    // Task base branch: blank means the repo default; bad names never reach the database.
    let mut blank_base = base();
    blank_base.agent_base_branch = Some("  ".into());
    blank_base.validate().unwrap();
    assert_eq!(blank_base.agent_base_branch, None);
    let mut release = base();
    release.agent_base_branch = Some(" release/1.2 ".into());
    release.validate().unwrap();
    assert_eq!(release.agent_base_branch.as_deref(), Some("release/1.2"));
    for bad in [
        "-x", "_x", ".x", "a..b", "a//b", "a/", "x.lock", "a b", "a@{1}", "a;rm",
    ] {
        let mut d = base();
        d.agent_base_branch = Some(bad.into());
        assert!(
            d.validate().unwrap_err().contains_key("agent_base_branch"),
            "{bad} should be rejected"
        );
    }
    let mut bad_workspace = ProjectInput {
        name: "Work".into(),
        git_repo_url: None,
        domain: None,
        agent_workspace_id: Some("x y".into()),
        agent_workspace_name: None,
        identifier_prefix: None,
    };
    assert!(
        bad_workspace
            .validate()
            .unwrap_err()
            .contains_key("agent_workspace_id")
    );
    bad_workspace.agent_workspace_id = Some("ws-1".into());
    assert!(bad_workspace.validate().is_ok());
}

#[test]
fn project_prefix_defaults_to_def_and_is_normalized() {
    use todos::domain::{DEFAULT_PREFIX, ProjectInput, valid_prefix};
    let base = |prefix: Option<&str>| ProjectInput {
        name: "Work".into(),
        git_repo_url: None,
        domain: None,
        agent_workspace_id: None,
        agent_workspace_name: None,
        identifier_prefix: prefix.map(Into::into),
    };
    let mut none = base(None);
    none.validate().unwrap();
    assert_eq!(none.identifier_prefix.as_deref(), Some(DEFAULT_PREFIX));
    let mut blank = base(Some("  "));
    blank.validate().unwrap();
    assert_eq!(blank.identifier_prefix.as_deref(), Some("DEF"));
    let mut lower = base(Some("api2"));
    lower.validate().unwrap();
    assert_eq!(lower.identifier_prefix.as_deref(), Some("API2"));
    for bad in ["A", "1AB", "AB-C", "ABCDEFGHIJK", "AB CD", "ÀB"] {
        assert!(
            base(Some(bad))
                .validate()
                .unwrap_err()
                .contains_key("identifier_prefix"),
            "{bad} should be rejected"
        );
    }
    assert!(valid_prefix("AB") && valid_prefix("A123456789"));
}
