use crate::{
    domain::{self, Errors, ProjectInput, TodoInput},
    infrastructure as repo,
};
use serde_json::{Value, json};
use uuid::Uuid;
#[derive(Debug)]
pub enum Error {
    Db(sqlx::Error),
    Validation(Errors),
    NotFound,
}
pub type Result<T> = std::result::Result<T, Error>;
impl From<sqlx::Error> for Error {
    fn from(e: sqlx::Error) -> Self {
        Self::Db(e)
    }
}
impl From<Errors> for Error {
    fn from(e: Errors) -> Self {
        Self::Validation(e)
    }
}
impl Error {
    pub fn field(key: &str, message: &str) -> Self {
        Self::Validation([(key.into(), message.into())].into())
    }
}
fn decode<T: serde::de::DeserializeOwned>(v: &Value) -> Result<T> {
    serde_json::from_value(v.clone()).map_err(|_| Error::field("request", "Dữ liệu không hợp lệ."))
}
fn id(s: Option<&&str>) -> Result<i64> {
    s.and_then(|s| s.parse().ok())
        .filter(|n| *n > 0)
        .ok_or(Error::NotFound)
}
fn flag(d: &Value, k: &str) -> Result<bool> {
    d[k].as_bool()
        .ok_or_else(|| Error::field(k, "Cần giá trị true/false."))
}
pub async fn page(pool: &sqlx::PgPool, owner: Uuid, path: &str) -> Result<Value> {
    let url = url::Url::parse(&format!("http://local{path}")).map_err(|_| Error::NotFound)?;
    if ["/", "/matrix"].contains(&url.path()) {
        return Ok(json!({"component":"Matrix","props":repo::snapshot(pool,owner).await?}));
    }
    let parts: Vec<_> = url.path().trim_matches('/').split('/').collect();
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    let projects = repo::projects(&mut tx, owner).await?;
    let result = if parts.first() == Some(&"todos") && parts.get(1) == Some(&"projects") {
        if parts.len() == 2 {
            json!({"component":"Projects/Index","props":{"projects":projects}})
        } else {
            let project = if parts.get(2) == Some(&"create") {
                Value::Null
            } else {
                let id = id(parts.get(2))?;
                projects
                    .iter()
                    .find(|p| p["id"] == id)
                    .cloned()
                    .ok_or(Error::NotFound)?
            };
            json!({"component":"Projects/Form","props":{"project":project}})
        }
    } else if parts[0] == "todos" {
        if parts.len() > 1 {
            let todo = if parts[1] == "create" {
                Value::Null
            } else {
                let id = id(parts.get(1))?;
                repo::task(&mut tx, owner, id).await?
            };
            json!({"component":"Todos/Form","props":{"todo":todo,"projects":projects,"statuses":domain::STATUSES,"priorities":domain::PRIORITIES}})
        } else {
            let query: std::collections::HashMap<_, _> = url.query_pairs().into_owned().collect();
            let search = query
                .get("search")
                .map(|s| s.chars().take(200).collect::<String>())
                .unwrap_or_default();
            let status = query.get("status").cloned().unwrap_or_default();
            let project = query.get("project_id").and_then(|v| v.parse::<i64>().ok());
            let requested_page = query
                .get("page")
                .and_then(|v| v.parse::<i64>().ok())
                .unwrap_or(1);
            let todos =
                repo::paginate(&mut tx, owner, project, &status, &search, requested_page).await?;
            json!({"component":"Todos/Index","props":{"todos":todos,"projects":projects,"filters":{"search":search,"status":status,"project_id":project},"statuses":domain::STATUSES,"priorities":domain::PRIORITIES}})
        }
    } else {
        return Err(Error::NotFound);
    };
    tx.commit().await?;
    Ok(result)
}
pub async fn action(
    pool: &sqlx::PgPool,
    owner: Uuid,
    path: &str,
    method: &str,
    data: &Value,
) -> Result<Value> {
    let parts: Vec<_> = path.trim_matches('/').split('/').collect();
    let mut tx = pool.begin().await?;
    repo::lock(&mut tx, owner).await?;
    if path.starts_with("/matrix/pomodoro/") {
        let operation = parts.last().copied().unwrap_or("");
        let expected = match operation {
            "settings" => "PUT",
            "active-todo" => "PATCH",
            "start" | "pause" | "skip" | "reset" | "focus" => "POST",
            _ => return Err(Error::NotFound),
        };
        if parts.len() != 3 || method != expected {
            return Err(Error::NotFound);
        }
        let result =
            pomodoro_action(&mut tx, owner, parts.last().copied().unwrap_or(""), data).await?;
        tx.commit().await?;
        return Ok(result);
    }
    let redirect = if path == "/matrix/backlog/promote" && method == "POST" {
        let items = data["items"]
            .as_array()
            .filter(|v| !v.is_empty() && v.len() <= 200)
            .ok_or_else(|| Error::field("items", "Chọn từ 1 đến 200 công việc."))?;
        let mut seen = std::collections::HashSet::new();
        for row in items {
            let id = row["id"]
                .as_i64()
                .filter(|id| seen.insert(*id))
                .ok_or_else(|| Error::field("items", "Công việc bị trùng hoặc không hợp lệ."))?;
            repo::ensure_task(&mut tx, owner, id).await?;
            let urgent = flag(row, "is_urgent")?;
            let important = flag(row, "is_important")?;
            let result=sqlx::query("UPDATE todos SET status='todo',is_urgent=$3,is_important=$4,updated_at=now() WHERE user_id=$1 AND id=$2 AND status='backlog'").bind(owner).bind(id).bind(urgent).bind(important).execute(&mut *tx).await?;
            if result.rows_affected() == 0 {
                return Err(Error::field(
                    "items",
                    "Chỉ có thể đưa việc từ backlog vào ma trận.",
                ));
            }
            repo::log_flags(&mut tx, owner, id, urgent, important).await?;
        }
        repo::bump(&mut tx, owner).await?;
        "/matrix"
    } else if parts.first() == Some(&"matrix")
        && parts.get(1) == Some(&"todos")
        && method == "PATCH"
    {
        let id = id(parts.get(2))?;
        repo::ensure_task(&mut tx, owner, id).await?;
        if parts.get(3) == Some(&"complete") {
            sqlx::query("UPDATE todos SET status='done',closed_at=now(),updated_at=now() WHERE user_id=$1 AND id=$2").bind(owner).bind(id).execute(&mut *tx).await?;
            sqlx::query("UPDATE todo_pomodoro_states SET active_todo_id=NULL,client_updated_at=GREATEST(client_updated_at+1,$3) WHERE user_id=$1 AND active_todo_id=$2").bind(owner).bind(id).bind(chrono::Utc::now().timestamp_millis()).execute(&mut *tx).await?;
        } else {
            let urgent = flag(data, "is_urgent")?;
            let important = flag(data, "is_important")?;
            let status = data["status"].as_str().unwrap_or("todo");
            if !["todo", "in_progress"].contains(&status) {
                return Err(Error::field("status", "Cần trạng thái đang hoạt động."));
            }
            sqlx::query("UPDATE todos SET is_urgent=$3,is_important=$4,status=$5,started_at=CASE WHEN $5='in_progress' THEN COALESCE(started_at,now()) ELSE started_at END,closed_at=NULL,updated_at=now() WHERE user_id=$1 AND id=$2").bind(owner).bind(id).bind(urgent).bind(important).bind(status).execute(&mut *tx).await?;
            repo::log_flags(&mut tx, owner, id, urgent, important).await?;
        }
        repo::bump(&mut tx, owner).await?;
        "/matrix"
    } else if parts.first() == Some(&"todos") && parts.get(1) == Some(&"projects") {
        match method {
            "POST" | "PUT" => {
                let mut input: ProjectInput = decode(data)?;
                input.validate()?;
                let id = if method == "PUT" {
                    Some(id(parts.get(2))?)
                } else {
                    None
                };
                let saved_id = repo::save_project(&mut tx, owner, id, &input).await?;
                if data["inline"] == true {
                    let project = repo::projects(&mut tx, owner)
                        .await?
                        .into_iter()
                        .find(|p| p["id"] == saved_id)
                        .ok_or(Error::NotFound)?;
                    tx.commit().await?;
                    return Ok(json!({"project":project}));
                }
            }
            "DELETE" => {
                let id = id(parts.get(2))?;
                repo::ensure_project(&mut tx, owner, id).await?;
                sqlx::query("UPDATE todo_projects SET deleted_at=now(),updated_at=now() WHERE user_id=$1 AND id=$2").bind(owner).bind(id).execute(&mut *tx).await?;
                sqlx::query("UPDATE todos SET project_id=NULL,updated_at=now() WHERE user_id=$1 AND project_id=$2").bind(owner).bind(id).execute(&mut *tx).await?;
                repo::bump(&mut tx, owner).await?;
            }
            _ => return Err(Error::NotFound),
        };
        "/todos/projects"
    } else if parts.first() == Some(&"todos") {
        match method {
            "POST" | "PUT" => {
                let mut input: TodoInput = decode(data)?;
                input.validate()?;
                let id = if method == "PUT" {
                    Some(id(parts.get(1))?)
                } else {
                    None
                };
                repo::save_task(&mut tx, owner, id, &input).await?;
            }
            "DELETE" => {
                let id = id(parts.get(1))?;
                repo::ensure_task(&mut tx, owner, id).await?;
                sqlx::query(
                    "UPDATE todos SET deleted_at=now(),updated_at=now() WHERE user_id=$1 AND id=$2",
                )
                .bind(owner)
                .bind(id)
                .execute(&mut *tx)
                .await?;
                sqlx::query("UPDATE todo_pomodoro_states SET active_todo_id=NULL WHERE user_id=$1 AND active_todo_id=$2").bind(owner).bind(id).execute(&mut *tx).await?;
                repo::bump(&mut tx, owner).await?;
            }
            _ => return Err(Error::NotFound),
        };
        if data["return_to"] == "matrix" {
            "/matrix"
        } else {
            "/todos"
        }
    } else {
        return Err(Error::NotFound);
    };
    tx.commit().await?;
    Ok(json!({"redirect":redirect,"flash":{"success":"Đã lưu thay đổi."}}))
}
async fn pomodoro_action(
    tx: &mut repo::Tx<'_>,
    owner: Uuid,
    action: &str,
    d: &Value,
) -> Result<Value> {
    let now = chrono::Utc::now().timestamp_millis();
    let mut s = repo::pomodoro(tx, owner).await?;
    repo::reconcile(tx, &mut s, now).await?;
    if let Some(id) = d.get("activeTodoId") {
        s.active_todo_id = if id.is_null() {
            None
        } else {
            let id = id
                .as_i64()
                .ok_or_else(|| Error::field("activeTodoId", "Công việc không hợp lệ."))?;
            repo::ensure_task(tx, owner, id).await?;
            Some(id)
        };
    }
    match action {
        "start" => s.start(now),
        "pause" => s.pause(now),
        "skip" => s.advance(now),
        "reset" => s.reset(now),
        "active-todo" => s.touch(now),
        "settings" => {
            let values = [
                ("focusMinutes", 1, 180),
                ("shortBreakMinutes", 1, 60),
                ("sessionsBeforeLongBreak", 1, 12),
                ("longBreakMinutes", 1, 60),
            ];
            let mut result = Vec::new();
            for (key, min, max) in values {
                let value = d[key]
                    .as_i64()
                    .filter(|n| (*n >= min) && (*n <= max))
                    .ok_or_else(|| Error::field(key, "Thời lượng không hợp lệ."))?;
                result.push(value as i32);
            }
            s.focus_minutes = result[0];
            s.short_break_minutes = result[1];
            s.sessions_before_long_break = result[2];
            s.long_break_minutes = result[3];
            s.reset(now);
        }
        "focus" => {
            let session = d["sessionUuid"]
                .as_str()
                .and_then(|s| s.parse::<Uuid>().ok());
            if session.is_some() && session == s.session_uuid && flag(d, "focused")? {
                s.last_focused_at = Some(chrono::Utc::now());
            }
        }
        _ => return Err(Error::NotFound),
    }
    repo::save_pomodoro(tx, &s).await?;
    Ok(s.payload())
}
