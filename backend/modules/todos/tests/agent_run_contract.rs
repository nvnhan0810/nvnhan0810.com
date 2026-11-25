//! Phase 4 contract: the v1 fixtures are read by the same checks the server runs. No database.
//! The Agent repo keeps copies of these files and parses them the same way.
use serde_json::Value;
use todos::domain::agent_run::{ReportInput, check_report, parse_claim};

fn fixture(name: &str) -> Value {
    let raw = match name {
        "claim_request" => include_str!("fixtures/integration/v1/claim_request.v1.json"),
        "claim_response" => include_str!("fixtures/integration/v1/claim_response.v1.json"),
        "report_running" => include_str!("fixtures/integration/v1/report_running.v1.json"),
        "report_succeeded" => include_str!("fixtures/integration/v1/report_succeeded.v1.json"),
        "report_failed" => include_str!("fixtures/integration/v1/report_failed.v1.json"),
        other => panic!("unknown fixture {other}"),
    };
    serde_json::from_str(raw).expect("fixture is valid JSON")
}

fn report(name: &str) -> ReportInput {
    serde_json::from_value(fixture(name)).expect("fixture matches ReportInput")
}

#[test]
fn claim_request_fixture_is_accepted() {
    let parsed = parse_claim(&fixture("claim_request")).expect("valid request");
    assert_eq!(parsed.workspaces, vec!["ws-1", "ws-2"]);
    assert_eq!(parsed.limit, 2);
    assert_eq!(parsed.lease_secs, 900);
}

#[test]
fn report_fixtures_pass_server_validation() {
    for name in ["report_running", "report_succeeded", "report_failed"] {
        check_report(report(name)).unwrap_or_else(|e| panic!("{name}: {e:?}"));
    }
}

#[test]
fn succeeded_fixture_carries_the_pr_and_conflict_files() {
    let checked = check_report(report("report_succeeded")).unwrap();
    assert_eq!(
        checked.pr_url.as_deref(),
        Some("https://github.com/o/r/pull/10")
    );
    assert!(checked.has_conflict);
    assert_eq!(checked.conflict_files, vec!["src/x.rs"]);
}

#[test]
fn claim_response_fixture_has_the_documented_shape() {
    let task = &fixture("claim_response")["tasks"][0];
    for key in [
        "id",
        "identifier",
        "claim_id",
        "lease_until",
        "quadrant",
        "priority",
        "due_at",
        "title",
        "description",
        "project",
        "repo",
        "depends_on",
        "todo_url",
        "attempt",
        "previous",
    ] {
        assert!(task.get(key).is_some(), "missing {key}");
    }
    assert_eq!(task["identifier"], "API-42");
    assert_eq!(task["project"]["agent_workspace_id"], "ws-1");
    assert_eq!(task["repo"]["agent_repo_id"], "repo-1");
    assert_eq!(
        task["depends_on"][0]["pr_url"],
        "https://github.com/o/r/pull/9"
    );
    assert_eq!(task["previous"]["has_conflict"], true);
}
