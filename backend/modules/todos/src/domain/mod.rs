pub mod pomodoro;
use chrono::{DateTime, FixedOffset};
use serde::{Deserialize, Serialize};
use std::collections::BTreeMap;

pub const STATUSES: [&str; 5] = ["backlog", "todo", "in_progress", "done", "rejected"];
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
        if e.is_empty() { Ok(()) } else { Err(e) }
    }
}
pub fn parse_datetime(s: &str) -> Option<DateTime<FixedOffset>> {
    DateTime::parse_from_rfc3339(s).ok()
}
#[derive(Debug, Deserialize, Serialize, Clone)]
pub struct ProjectInput {
    pub name: String,
    pub git_repo_url: Option<String>,
    pub domain: Option<String>,
}
impl ProjectInput {
    pub fn validate(&mut self) -> Result<(), Errors> {
        self.name = self.name.trim().into();
        let mut e = Errors::new();
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
