//! Phase 4: rules for Agent claims, leases and reports. Pure functions, no database.
//! The claim SQL in `application::agent_run` mirrors `claim_key`; a Postgres test checks they agree.
use super::{Errors, valid_agent_id};
use chrono::{DateTime, Utc};
use serde::Deserialize;
use serde_json::Value;
use uuid::Uuid;

pub const LEASE_DEFAULT_SECS: i64 = 900;
pub const LEASE_MIN_SECS: i64 = 60;
pub const LEASE_MAX_SECS: i64 = 3600;
pub const CLAIM_MAX_LIMIT: usize = 10;
pub const CLAIM_MAX_WORKSPACES: usize = 50;
pub const SUMMARY_MAX_CHARS: usize = 8000;
pub const TEXT_MAX_CHARS: usize = 2000;
pub const RESULT_START: &str = "<!-- agent-result:start -->";
pub const RESULT_END: &str = "<!-- agent-result:end -->";

// ---- Agent state -----------------------------------------------------------------------------

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum AgentState {
    Idle,
    Claimed,
    Running,
    Succeeded,
    Failed,
    Cancelled,
}

impl AgentState {
    pub fn parse(s: &str) -> Option<Self> {
        Some(match s {
            "idle" => Self::Idle,
            "claimed" => Self::Claimed,
            "running" => Self::Running,
            "succeeded" => Self::Succeeded,
            "failed" => Self::Failed,
            "cancelled" => Self::Cancelled,
            _ => return None,
        })
    }
    pub fn as_str(self) -> &'static str {
        match self {
            Self::Idle => "idle",
            Self::Claimed => "claimed",
            Self::Running => "running",
            Self::Succeeded => "succeeded",
            Self::Failed => "failed",
            Self::Cancelled => "cancelled",
        }
    }
    /// A live claim holds a lease and is the only state that heartbeats and reports may move.
    pub fn is_live(self) -> bool {
        matches!(self, Self::Claimed | Self::Running)
    }
}

/// `state` values the Agent may report (contract v1).
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Report {
    Running,
    Progress,
    Succeeded,
    Failed,
    Cancelled,
}

impl Report {
    pub fn parse(s: &str) -> Option<Self> {
        Some(match s {
            "running" => Self::Running,
            "progress" => Self::Progress,
            "succeeded" => Self::Succeeded,
            "failed" => Self::Failed,
            "cancelled" => Self::Cancelled,
            _ => return None,
        })
    }
    pub fn is_terminal(self) -> bool {
        matches!(self, Self::Succeeded | Self::Failed | Self::Cancelled)
    }
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Step {
    /// Apply the report and move to this state.
    Move(AgentState),
    /// The same terminal state was already recorded: answer 200 and change nothing.
    Repeat,
}

/// The state machine for reports. `None` is a conflict (409).
///
/// - `progress` keeps the state and only extends the lease.
/// - `running` is accepted from a live claim.
/// - A terminal report is accepted from a live claim, and repeated with the same state it is a no-op.
pub fn step(current: AgentState, report: Report) -> Option<Step> {
    match report {
        Report::Progress => current.is_live().then_some(Step::Move(current)),
        Report::Running => current.is_live().then_some(Step::Move(AgentState::Running)),
        Report::Succeeded | Report::Failed | Report::Cancelled => {
            let target = match report {
                Report::Succeeded => AgentState::Succeeded,
                Report::Failed => AgentState::Failed,
                _ => AgentState::Cancelled,
            };
            if current.is_live() {
                Some(Step::Move(target))
            } else if current == target {
                Some(Step::Repeat)
            } else {
                None
            }
        }
    }
}

/// Lease seconds from the request, clamped to the allowed range.
pub fn lease_secs(requested: Option<i64>) -> i64 {
    requested
        .unwrap_or(LEASE_DEFAULT_SECS)
        .clamp(LEASE_MIN_SECS, LEASE_MAX_SECS)
}

// ---- Claim order (Eisenhower, then priority, due date, created, id) ----------------------------

/// Same order as the claim SQL. Smaller sorts first.
pub type ClaimKey = (u8, u8, bool, Option<DateTime<Utc>>, DateTime<Utc>, i64);

/// 0 = Do, 1 = Schedule, 2 = Delegate, 3 = Eliminate (`super::quadrant`).
pub fn quadrant_rank(urgent: bool, important: bool) -> u8 {
    match (urgent, important) {
        (true, true) => 0,
        (false, true) => 1,
        (true, false) => 2,
        _ => 3,
    }
}

pub fn priority_rank(priority: &str) -> u8 {
    match priority {
        "urgent" => 0,
        "high" => 1,
        "medium" => 2,
        _ => 3,
    }
}

/// Tasks without a due date sort after tasks with one (`due_at NULLS LAST`).
pub fn claim_key(
    urgent: bool,
    important: bool,
    priority: &str,
    due_at: Option<DateTime<Utc>>,
    created_at: DateTime<Utc>,
    id: i64,
) -> ClaimKey {
    (
        quadrant_rank(urgent, important),
        priority_rank(priority),
        due_at.is_none(),
        due_at,
        created_at,
        id,
    )
}

// ---- Claim request ------------------------------------------------------------------------------

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct ClaimRequest {
    pub workspaces: Vec<String>,
    pub limit: usize,
    pub lease_secs: i64,
}

pub fn parse_claim(data: &Value) -> Result<ClaimRequest, Errors> {
    let mut e = Errors::new();
    let mut workspaces: Vec<String> = Vec::new();
    match data["workspaces"].as_array() {
        Some(list) if !list.is_empty() && list.len() <= CLAIM_MAX_WORKSPACES => {
            for item in list {
                match item.as_str() {
                    Some(id) if valid_agent_id(id) => {
                        if !workspaces.iter().any(|w| w == id) {
                            workspaces.push(id.to_owned());
                        }
                    }
                    _ => {
                        e.insert("workspaces".into(), "Workspace không hợp lệ.".into());
                        break;
                    }
                }
            }
        }
        _ => {
            e.insert("workspaces".into(), "Cần từ 1 đến 50 workspace.".into());
        }
    }
    let limit = data["limit"]
        .as_u64()
        .filter(|n| (1..=CLAIM_MAX_LIMIT as u64).contains(n))
        .map(|n| n as usize);
    if limit.is_none() {
        e.insert(
            "limit".into(),
            format!("Số task phải từ 1 đến {CLAIM_MAX_LIMIT}."),
        );
    }
    let lease = match data.get("lease_seconds") {
        None | Some(Value::Null) => Some(LEASE_DEFAULT_SECS),
        Some(v) => v.as_i64(),
    };
    if lease.is_none() {
        e.insert("lease_seconds".into(), "Lease phải là số giây.".into());
    }
    if !e.is_empty() {
        return Err(e);
    }
    Ok(ClaimRequest {
        workspaces,
        limit: limit.unwrap_or(1),
        lease_secs: lease_secs(lease),
    })
}

// ---- Report request -------------------------------------------------------------------------------

#[derive(Debug, Clone, Deserialize)]
pub struct ReportInput {
    pub claim_id: Uuid,
    pub state: String,
    #[serde(default)]
    pub message: Option<String>,
    #[serde(default)]
    pub chat_url: Option<String>,
    #[serde(default)]
    pub pr_url: Option<String>,
    #[serde(default)]
    pub branch: Option<String>,
    #[serde(default)]
    pub branch_pushed: Option<bool>,
    #[serde(default)]
    pub has_conflict: bool,
    #[serde(default)]
    pub conflict_files: Vec<String>,
    #[serde(default)]
    pub failed_step: Option<String>,
    #[serde(default)]
    pub error: Option<String>,
    #[serde(default)]
    pub summary_md: Option<String>,
}

/// Checked report fields. Empty optional strings become `None`.
#[derive(Debug, Clone)]
pub struct Checked {
    pub claim_id: Uuid,
    pub report: Report,
    pub message: Option<String>,
    pub chat_url: Option<String>,
    pub pr_url: Option<String>,
    pub branch: Option<String>,
    pub branch_pushed: Option<bool>,
    pub has_conflict: bool,
    pub conflict_files: Vec<String>,
    pub failed_step: Option<String>,
    pub error: Option<String>,
    pub summary: Option<String>,
}

pub fn check_report(input: ReportInput) -> Result<Checked, Errors> {
    let mut e = Errors::new();
    let report = Report::parse(&input.state);
    if report.is_none() {
        e.insert("state".into(), "Trạng thái báo cáo không hợp lệ.".into());
    }
    let text = |v: Option<String>, max: usize| -> Option<String> {
        crate::domain::clean_id(v).map(|s| s.chars().take(max).collect())
    };
    let chat_url = text(input.chat_url, TEXT_MAX_CHARS);
    let pr_url = text(input.pr_url, TEXT_MAX_CHARS);
    for (key, value) in [("chat_url", &chat_url), ("pr_url", &pr_url)] {
        if value.as_deref().is_some_and(|u| !valid_http_url(u)) {
            e.insert(key.into(), "Cần URL HTTP(S) hợp lệ.".into());
        }
    }
    let branch = text(input.branch, 200);
    if branch.as_deref().is_some_and(|b| !valid_branch(b)) {
        e.insert("branch".into(), "Tên nhánh không hợp lệ.".into());
    }
    if input.conflict_files.len() > 200
        || input.conflict_files.iter().any(|f| f.chars().count() > 300)
    {
        e.insert(
            "conflict_files".into(),
            "Danh sách file conflict quá dài.".into(),
        );
    }
    // Whether a succeeded report needs a PR depends on the task's repo: checked in `report`.
    if !e.is_empty() {
        return Err(e);
    }
    Ok(Checked {
        claim_id: input.claim_id,
        report: report.unwrap_or(Report::Progress),
        message: text(input.message, TEXT_MAX_CHARS),
        chat_url,
        pr_url,
        branch,
        branch_pushed: input.branch_pushed,
        has_conflict: input.has_conflict,
        conflict_files: input
            .conflict_files
            .into_iter()
            .map(|f: String| f.chars().take(300).collect::<String>())
            .filter(|f| !f.trim().is_empty())
            .collect(),
        failed_step: text(input.failed_step, 50),
        error: text(input.error, TEXT_MAX_CHARS),
        summary: text(input.summary_md, SUMMARY_MAX_CHARS * 4).map(|s| clean_summary(&s)),
    })
}

/// `pr-updated` body: the Agent (or the chat's "Đẩy lên PR" button) reports the PR state.
#[derive(Debug, Clone, Deserialize)]
pub struct PrUpdateInput {
    pub pr_url: String,
    #[serde(default)]
    pub has_conflict: bool,
    #[serde(default)]
    pub conflict_files: Vec<String>,
    #[serde(default)]
    pub summary_md: Option<String>,
}

pub fn check_pr_update(input: PrUpdateInput) -> Result<PrUpdateInput, Errors> {
    let mut e = Errors::new();
    if !valid_http_url(&input.pr_url) {
        e.insert("pr_url".into(), "Cần URL HTTP(S) hợp lệ.".into());
    }
    if input.conflict_files.len() > 200
        || input.conflict_files.iter().any(|f| f.chars().count() > 300)
    {
        e.insert(
            "conflict_files".into(),
            "Danh sách file conflict quá dài.".into(),
        );
    }
    if e.is_empty() { Ok(input) } else { Err(e) }
}

pub fn valid_http_url(raw: &str) -> bool {
    raw.len() <= TEXT_MAX_CHARS
        && url::Url::parse(raw).is_ok_and(|u| {
            matches!(u.scheme(), "http" | "https")
                && u.host_str().is_some()
                && u.username().is_empty()
                && u.password().is_none()
        })
}

/// Agent branches look like `agent/todo-123-slug`. Keep to a conservative charset.
pub fn valid_branch(branch: &str) -> bool {
    (1..=200).contains(&branch.len())
        && !branch.starts_with(['-', '/'])
        && !branch.contains("..")
        && branch
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-_./".contains(&b))
}

/// Agent summaries are untrusted text. Remove our markers so they cannot close the block early.
pub fn clean_summary(text: &str) -> String {
    text.replace(RESULT_START, "")
        .replace(RESULT_END, "")
        .chars()
        .take(SUMMARY_MAX_CHARS)
        .collect::<String>()
        .trim()
        .to_owned()
}

// ---- Result block inside the task description -----------------------------------------------

/// Everything the result block shows. `headline` is the first line, e.g. "✅ Thành công".
#[derive(Debug, Clone, Copy)]
pub struct ResultView<'a> {
    pub headline: &'a str,
    pub branch: Option<&'a str>,
    pub chat_url: Option<&'a str>,
    pub pr_url: Option<&'a str>,
    pub has_conflict: bool,
    pub conflict_files: &'a [String],
    pub failed_step: Option<&'a str>,
    pub error: Option<&'a str>,
    pub summary: Option<&'a str>,
}

/// Inner markdown of the result block (without markers). Stored in `agent_result_md`.
pub fn render_result(v: &ResultView) -> String {
    let mut lines = vec![format!("**🤖 Agent · {}**", v.headline)];
    if let Some(b) = v.branch {
        lines.push(format!("- Nhánh: `{}`", b.replace('`', "'")));
    }
    if let Some(u) = v.pr_url {
        lines.push(format!("- PR: {u}"));
    }
    if let Some(u) = v.chat_url {
        lines.push(format!("- Chat Agent: {u}"));
    }
    if v.has_conflict {
        let files: Vec<String> = v
            .conflict_files
            .iter()
            .map(|f| format!("`{}`", f.replace('`', "'")))
            .collect();
        let at = if files.is_empty() {
            String::new()
        } else {
            format!(" ở {}", files.join(", "))
        };
        lines.push(format!("- ⚠️ Conflict với `main`{at}"));
        lines.push(
            "  Cách xử lý: kéo task về **todo** để Agent cập nhật nhánh theo `main`, hoặc mở chat của task để sửa tay rồi bấm **Đẩy lên PR**."
                .into(),
        );
    }
    if let Some(s) = v.failed_step {
        lines.push(format!("- Bước lỗi: `{}`", s.replace('`', "'")));
    }
    if let Some(e) = v.error {
        lines.push(format!("- Lỗi: {e}"));
    }
    if let Some(s) = v.summary.filter(|s| !s.is_empty()) {
        lines.push(String::new());
        lines.push(s.to_owned());
    }
    lines.join("\n")
}

pub fn wrap_result(inner: &str) -> String {
    format!("{RESULT_START}\n{inner}\n{RESULT_END}")
}

/// Remove every result block (and an unterminated one, to the end of the text).
pub fn strip_result(text: &str) -> String {
    let mut out = String::with_capacity(text.len());
    let mut rest = text;
    loop {
        let Some(start) = rest.find(RESULT_START) else {
            out.push_str(rest);
            break;
        };
        out.push_str(&rest[..start]);
        let after = &rest[start..];
        match after.find(RESULT_END) {
            Some(end) => rest = &after[end + RESULT_END.len()..],
            None => break,
        }
    }
    out.trim_end().to_owned()
}

/// The description as stored: the user's text, then the result block if there is one.
/// The user's text never keeps a stale block, so the block is always the server's copy.
pub fn with_result(description: Option<&str>, inner: Option<&str>) -> Option<String> {
    let body = strip_result(description.unwrap_or(""));
    match inner.filter(|i| !i.is_empty()) {
        Some(inner) if body.is_empty() => Some(wrap_result(inner)),
        Some(inner) => Some(format!("{body}\n\n{}", wrap_result(inner))),
        None if body.is_empty() => None,
        None => Some(body),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use chrono::TimeZone;

    fn at(day: u32) -> DateTime<Utc> {
        Utc.with_ymd_and_hms(2026, 11, day, 0, 0, 0).unwrap()
    }

    #[test]
    fn report_state_machine() {
        use AgentState::*;
        use Report as R;
        assert_eq!(step(Claimed, R::Running), Some(Step::Move(Running)));
        assert_eq!(step(Running, R::Running), Some(Step::Move(Running)));
        assert_eq!(step(Claimed, R::Progress), Some(Step::Move(Claimed)));
        assert_eq!(step(Running, R::Progress), Some(Step::Move(Running)));
        assert_eq!(step(Running, R::Succeeded), Some(Step::Move(Succeeded)));
        assert_eq!(step(Claimed, R::Failed), Some(Step::Move(Failed)));
        assert_eq!(step(Running, R::Cancelled), Some(Step::Move(Cancelled)));
        // Repeats of the same terminal state are no-ops.
        assert_eq!(step(Succeeded, R::Succeeded), Some(Step::Repeat));
        assert_eq!(step(Failed, R::Failed), Some(Step::Repeat));
        // Everything else after a terminal state is a conflict.
        assert_eq!(step(Succeeded, R::Failed), None);
        assert_eq!(step(Failed, R::Running), None);
        assert_eq!(step(Succeeded, R::Progress), None);
        assert_eq!(step(Idle, R::Running), None);
        assert_eq!(step(Cancelled, R::Succeeded), None);
        assert_eq!(step(Idle, R::Succeeded), None);
    }

    #[test]
    fn claim_order_is_eisenhower_then_priority_due_created_id() {
        let now = at(10);
        let mut rows = [
            // (urgent, important, priority, due, created, id)
            (false, false, "urgent", None, now, 1), // eliminate
            (true, false, "high", None, now, 2),    // delegate
            (false, true, "low", Some(at(20)), now, 3), // schedule, later due
            (false, true, "low", Some(at(12)), now, 4), // schedule, sooner due
            (true, true, "medium", None, now, 5),   // do, medium
            (true, true, "urgent", None, now, 6),   // do, urgent
            (true, true, "urgent", Some(at(11)), now, 7), // do, urgent with due
            (true, true, "urgent", None, at(9), 8), // do, urgent, created earlier
        ];
        rows.sort_by_key(|r| claim_key(r.0, r.1, r.2, r.3, r.4, r.5));
        let ids: Vec<i64> = rows.iter().map(|r| r.5).collect();
        assert_eq!(ids, vec![7, 8, 6, 5, 4, 3, 2, 1]);
    }

    #[test]
    fn claim_request_validation() {
        let ok = parse_claim(&serde_json::json!({"workspaces":["ws-1","ws-1","ws-2"],"limit":3}))
            .unwrap();
        assert_eq!(ok.workspaces, vec!["ws-1", "ws-2"]);
        assert_eq!(ok.limit, 3);
        assert_eq!(ok.lease_secs, LEASE_DEFAULT_SECS);
        assert!(parse_claim(&serde_json::json!({"workspaces":[],"limit":1})).is_err());
        assert!(parse_claim(&serde_json::json!({"workspaces":["a b"],"limit":1})).is_err());
        assert!(parse_claim(&serde_json::json!({"workspaces":["ws"],"limit":0})).is_err());
        assert!(parse_claim(&serde_json::json!({"workspaces":["ws"],"limit":11})).is_err());
        assert!(parse_claim(&serde_json::json!({"workspaces":["ws"]})).is_err());
        let clamped =
            parse_claim(&serde_json::json!({"workspaces":["ws"],"limit":1,"lease_seconds":5}))
                .unwrap();
        assert_eq!(clamped.lease_secs, LEASE_MIN_SECS);
    }

    #[test]
    fn report_validation_rules() {
        let base = |state: &str, pr: Option<&str>| ReportInput {
            claim_id: Uuid::nil(),
            state: state.into(),
            message: None,
            chat_url: None,
            pr_url: pr.map(str::to_owned),
            branch: Some("agent/todo-1-fix".into()),
            branch_pushed: None,
            has_conflict: false,
            conflict_files: vec![],
            failed_step: None,
            error: None,
            summary_md: None,
        };
        // Whether a succeeded report needs a PR depends on the task's repo (see `report`).
        assert!(check_report(base("succeeded", None)).is_ok());
        assert!(check_report(base("succeeded", Some("https://github.com/o/r/pull/1"))).is_ok());
        assert!(
            check_report(base("failed", None)).is_ok(),
            "failed may have no PR"
        );
        assert!(check_report(base("paused", None)).is_err());
        assert!(check_report(base("running", None)).is_ok());
        let mut bad_branch = base("running", None);
        bad_branch.branch = Some("agent/../x".into());
        assert!(check_report(bad_branch).is_err());
        let mut bad_url = base("failed", None);
        bad_url.chat_url = Some("javascript:alert(1)".into());
        assert!(check_report(bad_url).is_err());
    }

    #[test]
    fn result_block_round_trip_keeps_user_text_and_replaces_old_block() {
        let files = vec!["src/x.rs".to_owned()];
        let inner = render_result(&ResultView {
            headline: "✅ Thành công",
            branch: Some("agent/todo-1-fix"),
            chat_url: Some("https://agent.example/chats/9"),
            pr_url: Some("https://github.com/o/r/pull/1"),
            has_conflict: true,
            conflict_files: &files,
            failed_step: None,
            error: None,
            summary: Some("Đã sửa."),
        });
        assert!(inner.contains("⚠️ Conflict với `main` ở `src/x.rs`"));
        let stored = with_result(Some("Việc của tôi\n"), Some(&inner)).unwrap();
        assert!(stored.starts_with("Việc của tôi\n\n<!-- agent-result:start -->"));
        assert_eq!(strip_result(&stored), "Việc của tôi");

        // Saving again with the block echoed back, then with a new result: still one block.
        let again = with_result(Some(&stored), Some("NEW")).unwrap();
        assert_eq!(again.matches(RESULT_START).count(), 1);
        assert!(again.ends_with("NEW\n<!-- agent-result:end -->"));
        assert_eq!(strip_result(&again), "Việc của tôi");

        // No result yet: only the user text; empty text and no result: nothing stored.
        assert_eq!(with_result(Some("abc"), None).as_deref(), Some("abc"));
        assert_eq!(with_result(Some("  "), None), None);
        assert_eq!(with_result(None, None), None);
        assert_eq!(
            with_result(None, Some("R")).as_deref(),
            Some("<!-- agent-result:start -->\nR\n<!-- agent-result:end -->")
        );
    }

    #[test]
    fn unterminated_block_is_dropped_and_summary_cannot_close_the_block() {
        assert_eq!(strip_result("a\n<!-- agent-result:start -->\nno end"), "a");
        let cleaned = clean_summary("x <!-- agent-result:end --> y");
        assert!(!cleaned.contains("agent-result"));
    }
}
