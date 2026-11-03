use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use uuid::Uuid;
#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct State {
    pub user_id: Uuid,
    pub focus_minutes: i32,
    pub short_break_minutes: i32,
    pub sessions_before_long_break: i32,
    pub long_break_minutes: i32,
    pub phase: String,
    pub remaining_ms: i64,
    pub ends_at: Option<i64>,
    pub focus_count: i32,
    pub active_todo_id: Option<i64>,
    pub is_running: bool,
    pub session_uuid: Option<Uuid>,
    pub last_focused_at: Option<chrono::DateTime<chrono::Utc>>,
    pub client_updated_at: i64,
}
impl State {
    pub fn duration(&self) -> i64 {
        i64::from(match self.phase.as_str() {
            "short_break" => self.short_break_minutes,
            "long_break" => self.long_break_minutes,
            _ => self.focus_minutes,
        }) * 60_000
    }
    pub fn advance(&mut self, now: i64) {
        if self.phase == "focus" {
            self.focus_count += 1;
            self.phase = if self.focus_count >= self.sessions_before_long_break {
                self.focus_count = 0;
                "long_break"
            } else {
                "short_break"
            }
            .into();
        } else {
            self.phase = "focus".into();
        }
        self.remaining_ms = self.duration();
        self.ends_at = self.is_running.then_some(now + self.remaining_ms);
        self.session_uuid = self.is_running.then(Uuid::new_v4);
        self.touch(now);
    }
    pub fn touch(&mut self, now: i64) {
        self.client_updated_at = now.max(self.client_updated_at + 1);
    }
    pub fn pause(&mut self, now: i64) {
        if self.is_running {
            self.remaining_ms = (self.ends_at.unwrap_or(now) - now).max(0);
        }
        self.is_running = false;
        self.ends_at = None;
        self.session_uuid = None;
        self.touch(now);
    }
    pub fn start(&mut self, now: i64) {
        if !self.is_running {
            self.is_running = true;
            self.remaining_ms = self.remaining_ms.max(1);
            self.ends_at = Some(now + self.remaining_ms);
            self.session_uuid = Some(Uuid::new_v4());
            self.touch(now);
        }
    }
    pub fn reset(&mut self, now: i64) {
        self.is_running = false;
        self.phase = "focus".into();
        self.focus_count = 0;
        self.remaining_ms = self.duration();
        self.ends_at = None;
        self.session_uuid = None;
        self.touch(now);
    }
    pub fn payload(&self) -> Value {
        json!({"settings":{"focusMinutes":self.focus_minutes,"shortBreakMinutes":self.short_break_minutes,"sessionsBeforeLongBreak":self.sessions_before_long_break,"longBreakMinutes":self.long_break_minutes},"runtime":{"phase":self.phase,"remainingMs":self.remaining_ms,"endsAt":self.ends_at,"focusCount":self.focus_count,"activeTodoId":self.active_todo_id,"isRunning":self.is_running,"updatedAt":self.client_updated_at,"sessionUuid":self.session_uuid},"version":self.client_updated_at,"serverNow":chrono::Utc::now().timestamp_millis()})
    }
}
