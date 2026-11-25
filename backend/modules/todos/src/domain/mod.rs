pub mod pomodoro;
use chrono::{DateTime, FixedOffset};
use serde::{Deserialize, Serialize};
use std::collections::BTreeMap;

pub const STATUSES: [&str; 6] = [
    "backlog",
    "todo",
    "in_progress",
    "in_review",
    "done",
    "rejected",
];
pub const PRIORITIES: [&str; 4] = ["low", "medium", "high", "urgent"];
pub type Errors = BTreeMap<String, String>;
#[derive(Debug, Deserialize, Serialize, Clone)]
pub struct TodoInput {
    pub project_id: Option<i64>,
    pub title: String,
    pub description: Option<String>,
    pub status: String,
    pub priority: String,
    pub due_at: Option<String>,
    #[serde(default)]
    pub is_urgent: bool,
    #[serde(default)]
    pub is_important: bool,
    /// Tasks (same project) that must be `done` before this one may run. `None` keeps the current set.
    #[serde(default)]
    pub depends_on: Option<Vec<i64>>,
    /// Agent repo this task runs in (must belong to the project's Agent workspace).
    #[serde(default)]
    pub agent_repo_id: Option<String>,
    /// Display snapshot; always overwritten by the server from the Agent catalog.
    #[serde(default)]
    pub agent_repo_name: Option<String>,
    /// Let the Agent pick this task up automatically (needs a repo).
    #[serde(default)]
    pub agent_auto: bool,
}
impl TodoInput {
    pub fn validate(&mut self) -> Result<(), Errors> {
        self.title = self.title.trim().into();
        let mut e = Errors::new();
        if self.title.is_empty() || self.title.chars().count() > 255 {
            e.insert("title".into(), "Tiêu đề phải có từ 1 đến 255 ký tự.".into());
        }
        if !STATUSES.contains(&self.status.as_str()) {
            e.insert("status".into(), "Trạng thái không hợp lệ.".into());
        }
        if !PRIORITIES.contains(&self.priority.as_str()) {
            e.insert("priority".into(), "Độ ưu tiên không hợp lệ.".into());
        }
        if self.description.as_ref().is_some_and(|s| s.len() > 100_000) {
            e.insert("description".into(), "Mô tả quá dài.".into());
        }
        if self
            .due_at
            .as_ref()
            .filter(|s| !s.is_empty())
            .is_some_and(|due| parse_datetime(due).is_none())
        {
            e.insert("due_at".into(), "Ngày hạn không hợp lệ.".into());
        }
        if self
            .depends_on
            .as_ref()
            .is_some_and(|d| d.len() > MAX_DEPENDENCIES)
        {
            e.insert("depends_on".into(), "Quá nhiều công việc phụ thuộc.".into());
        }
        self.agent_repo_id = clean_id(self.agent_repo_id.take());
        if self.agent_repo_id.is_none() {
            self.agent_repo_name = None;
        }
        if self
            .agent_repo_id
            .as_deref()
            .is_some_and(|id| !valid_agent_id(id))
        {
            e.insert(
                "agent_repo_id".into(),
                "Repository Agent không hợp lệ.".into(),
            );
        } else if self.agent_repo_id.is_some() && self.project_id.is_none() {
            e.insert(
                "agent_repo_id".into(),
                "Cần chọn project trước khi chọn repository.".into(),
            );
        } else if self.agent_auto && self.agent_repo_id.is_none() {
            e.insert(
                "agent_auto".into(),
                "Cần chọn repository để Agent tự động xử lý.".into(),
            );
        }
        if e.is_empty() { Ok(()) } else { Err(e) }
    }
}
/// Trim; empty means "not set".
pub fn clean_id(v: Option<String>) -> Option<String> {
    v.map(|s| s.trim().to_owned()).filter(|s| !s.is_empty())
}
/// Agent ids are opaque (UUID today); accept a conservative charset and length only.
pub fn valid_agent_id(id: &str) -> bool {
    (1..=100).contains(&id.len())
        && id
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-_.".contains(&b))
}
pub const MAX_DEPENDENCIES: usize = 50;
/// Sort + dedupe a dependency list, rejecting self references and non-positive ids.
pub fn normalize_dependencies(todo: Option<i64>, ids: &[i64]) -> Result<Vec<i64>, &'static str> {
    if ids.len() > MAX_DEPENDENCIES {
        return Err("Quá nhiều công việc phụ thuộc.");
    }
    if ids.iter().any(|id| *id <= 0) {
        return Err("Công việc phụ thuộc không hợp lệ.");
    }
    if todo.is_some_and(|t| ids.contains(&t)) {
        return Err("Công việc không thể phụ thuộc vào chính nó.");
    }
    let mut v = ids.to_vec();
    v.sort_unstable();
    v.dedup();
    Ok(v)
}
pub fn parse_datetime(s: &str) -> Option<DateTime<FixedOffset>> {
    DateTime::parse_from_rfc3339(s).ok()
}
#[derive(Debug, Deserialize, Serialize, Clone)]
pub struct ProjectInput {
    pub name: String,
    pub git_repo_url: Option<String>,
    pub domain: Option<String>,
    /// Agent workspace this project maps to.
    #[serde(default)]
    pub agent_workspace_id: Option<String>,
    /// Display snapshot; always overwritten by the server from the Agent catalog.
    #[serde(default)]
    pub agent_workspace_name: Option<String>,
}
impl ProjectInput {
    pub fn validate(&mut self) -> Result<(), Errors> {
        self.name = self.name.trim().into();
        let mut e = Errors::new();
        self.agent_workspace_id = clean_id(self.agent_workspace_id.take());
        if self.agent_workspace_id.is_none() {
            self.agent_workspace_name = None;
        } else if self
            .agent_workspace_id
            .as_deref()
            .is_some_and(|id| !valid_agent_id(id))
        {
            e.insert(
                "agent_workspace_id".into(),
                "Workspace Agent không hợp lệ.".into(),
            );
        }
        if self.name.is_empty() || self.name.chars().count() > 255 {
            e.insert(
                "name".into(),
                "Tên project phải có từ 1 đến 255 ký tự.".into(),
            );
        }
        for (key, v) in [
            ("git_repo_url", &self.git_repo_url),
            ("domain", &self.domain),
        ] {
            if let Some(v) = v.as_ref().filter(|v| !v.is_empty())
                && !url::Url::parse(v).is_ok_and(|u| {
                    matches!(u.scheme(), "http" | "https")
                        && u.host_str().is_some()
                        && u.username().is_empty()
                        && u.password().is_none()
                })
            {
                e.insert(key.into(), "Cần URL HTTP(S) hợp lệ.".into());
            }
        }
        if e.is_empty() { Ok(()) } else { Err(e) }
    }
}
pub fn quadrant(urgent: bool, important: bool) -> &'static str {
    match (urgent, important) {
        (true, true) => "do",
        (false, true) => "schedule",
        (true, false) => "delegate",
        _ => "eliminate",
    }
}
