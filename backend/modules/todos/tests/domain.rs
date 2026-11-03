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
