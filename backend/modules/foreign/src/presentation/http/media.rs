use super::{
    routes::ForeignState,
    vocabulary::{ApiError, internal, user},
};
use axum::{
    Json,
    extract::State,
    http::{HeaderMap, StatusCode},
};
use serde_json::{Value, json};
pub fn bad(message: &str) -> ApiError {
    (StatusCode::BAD_REQUEST, Json(json!({"error":message})))
}
pub fn missing() -> ApiError {
    (StatusCode::NOT_FOUND, Json(json!({"error":"not_found"})))
}
pub fn youtube_id(value: &str) -> Option<String> {
    let url = reqwest::Url::parse(value).ok()?;
    if url.scheme() != "https" || !url.username().is_empty() || url.password().is_some() {
        return None;
    }
    let id = match url.host_str()? {
        "youtu.be" => url.path().trim_start_matches('/').to_owned(),
        "youtube.com" | "www.youtube.com" | "m.youtube.com" => {
            if url.path() == "/watch" {
                url.query_pairs()
                    .find(|(key, _)| key == "v")?
                    .1
                    .into_owned()
            } else {
                let mut parts = url.path().trim_matches('/').split('/');
                if !matches!(parts.next()?, "shorts" | "embed") {
                    return None;
                }
                parts.next()?.to_owned()
            }
        }
        _ => return None,
    };
    (id.len() == 11
        && id
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b == b'_' || b == b'-'))
    .then_some(id)
}
pub async fn preview(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Json(data): Json<Value>,
) -> Result<Json<Value>, ApiError> {
    user(&state, &headers).await?;
    let id = youtube_id(data["url"].as_str().unwrap_or(""))
        .ok_or_else(|| bad("Use a valid HTTPS YouTube URL."))?;
    let canonical = format!("https://www.youtube.com/watch?v={id}");
    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(10))
        .redirect(reqwest::redirect::Policy::none())
        .build()
        .map_err(|_| bad("Preview unavailable"))?;
    let response = client
        .get("https://www.youtube.com/oembed")
        .query(&[("url", canonical.as_str()), ("format", "json")])
        .send()
        .await
        .map_err(|_| bad("Could not fetch YouTube preview. Please retry."))?;
    if !response.status().is_success() {
        return Err(bad("YouTube preview unavailable for this video."));
    }
    let body = crate::infrastructure::captions::bounded_response(response, 64 * 1024)
        .await
        .map_err(|error| bad(&error))?;
    let payload: Value = serde_json::from_slice(&body).map_err(|_| bad("Preview unavailable"))?;
    Ok(Json(
        json!({"data":{"url":canonical,"title":payload["title"],"source_id":id,"thumbnail_url":format!("https://i.ytimg.com/vi/{id}/hqdefault.jpg")}}),
    ))
}
pub async fn get(pool: &sqlx::PgPool, user: uuid::Uuid, id: i64) -> Result<Value, ApiError> {
    sqlx::query_scalar("SELECT payload || jsonb_build_object('id',id) FROM foreign_media WHERE id=$1 AND user_id=$2").bind(id).bind(user).fetch_optional(pool).await.map_err(internal)?.ok_or_else(missing)
}
pub async fn action(
    state: &ForeignState,
    user: uuid::Uuid,
    path: &str,
    data: &Value,
    method: &str,
) -> Result<String, ApiError> {
    if path == "/home/media/youtube" && method == "POST" {
        let id = youtube_id(data["url"].as_str().unwrap_or(""))
            .ok_or_else(|| bad("Invalid YouTube URL"))?;
        let title = data["title"].as_str().unwrap_or("").trim();
        if title.is_empty() || title.len() > 255 {
            return Err(bad("Title must contain 1–255 characters."));
        }
        let difficulty = data["difficulty"].as_str().unwrap_or("intermediate");
        if !["beginner", "intermediate", "advanced"].contains(&difficulty) {
            return Err(bad("Invalid difficulty"));
        }
        let payload = json!({"title":title,"type":"youtube","url":format!("https://www.youtube.com/watch?v={id}"),"source_id":id,"difficulty":difficulty,"transcript":"","transcript_segments":[],"thumbnail_url":format!("https://i.ytimg.com/vi/{id}/hqdefault.jpg")});
        let mut tx = state.pool.begin().await.map_err(internal)?;
        sqlx::query("SELECT id FROM identity_users WHERE id=$1 FOR UPDATE")
            .bind(user)
            .fetch_one(&mut *tx)
            .await
            .map_err(internal)?;
        let existing: Option<i64> = sqlx::query_scalar("SELECT min(id) FROM foreign_media WHERE user_id=$1 AND payload->>'type'='youtube' AND payload->>'source_id'=$2")
            .bind(user).bind(&id).fetch_one(&mut *tx).await.map_err(internal)?;
        if let Some(id) = existing {
            tx.commit().await.map_err(internal)?;
            return Ok(format!("/home/media/{id}"));
        }
        let id: i64 = sqlx::query_scalar(
            "INSERT INTO foreign_media(user_id,payload) VALUES($1,$2) RETURNING id",
        )
        .bind(user)
        .bind(payload)
        .fetch_one(&mut *tx)
        .await
        .map_err(internal)?;
        tx.commit().await.map_err(internal)?;
        crate::infrastructure::transcript_queue::enqueue(
            &state.pool,
            &state.transcript_queue,
            id,
            state.transcript_queue.dispatch.automatic,
        )
        .await
        .map_err(internal)?;
        return Ok(format!("/home/media/{id}"));
    }
    let pieces: Vec<_> = path.trim_matches('/').split('/').collect();
    let id = pieces
        .get(2)
        .and_then(|id| id.parse::<i64>().ok())
        .ok_or_else(missing)?;
    let mut tx = state.pool.begin().await.map_err(internal)?;
    let mut payload: Value = sqlx::query_scalar(
        "SELECT payload FROM foreign_media WHERE id=$1 AND user_id=$2 FOR UPDATE",
    )
    .bind(id)
    .bind(user)
    .fetch_optional(&mut *tx)
    .await
    .map_err(internal)?
    .ok_or_else(missing)?;
    if pieces.get(3) == Some(&"captions") && method == "POST" {
        if payload["type"] != "youtube" || payload["source_id"].as_str().is_none() {
            return Err(bad("Captions require a YouTube video"));
        }
        if !payload["transcript"]
            .as_str()
            .unwrap_or("")
            .trim()
            .is_empty()
            || payload["transcript_segments"]
                .as_array()
                .is_some_and(|segments| !segments.is_empty())
        {
            return Err(bad(
                "Transcript already exists. Use Edit to preserve your changes.",
            ));
        }
        tx.commit().await.map_err(internal)?;
        crate::infrastructure::transcript_queue::enqueue(
            &state.pool,
            &state.transcript_queue,
            id,
            state.transcript_queue.dispatch.manual,
        )
        .await
        .map_err(internal)?;
        return Ok(format!("/home/media/{id}"));
    }
    let redirect = if method == "DELETE" && pieces.len() == 3 {
        sqlx::query("DELETE FROM foreign_media WHERE id=$1 AND user_id=$2")
            .bind(id)
            .bind(user)
            .execute(&mut *tx)
            .await
            .map_err(internal)?;
        "/home/media".to_owned()
    } else if pieces.get(3) == Some(&"transcript") && method == "PUT" {
        let segments = data["segments"]
            .as_array()
            .ok_or_else(|| bad("Invalid transcript"))?;
        if segments.len() > 10000 {
            return Err(bad("Too many subtitle lines"));
        }
        let mut normalized = Vec::new();
        let mut previous = 0.0;
        for segment in segments {
            let start = segment["start"]
                .as_f64()
                .ok_or_else(|| bad("Invalid subtitle time"))?;
            let end = segment["end"]
                .as_f64()
                .ok_or_else(|| bad("Invalid subtitle time"))?;
            let text = segment["text"].as_str().unwrap_or("").trim();
            if start < 0.0
                || end <= start
                || start < previous
                || text.is_empty()
                || text.len() > 10000
            {
                return Err(bad(
                    "Subtitle lines need increasing times, an end after start, and text.",
                ));
            }
            previous = start;
            normalized.push(json!({"start":start,"end":end,"text":text}));
        }
        payload["transcript_task_id"] = Value::Null;
        payload["transcript_status"] = json!("cancelled");
        payload["transcript_error"] = Value::Null;
        payload["transcript"] = json!(
            normalized
                .iter()
                .filter_map(|s| s["text"].as_str())
                .collect::<Vec<_>>()
                .join("\n")
        );
        payload["transcript_segments"] = json!(normalized);
        sqlx::query(
            "UPDATE foreign_media SET payload=$3,updated_at=now() WHERE id=$1 AND user_id=$2",
        )
        .bind(id)
        .bind(user)
        .bind(payload)
        .execute(&mut *tx)
        .await
        .map_err(internal)?;
        format!("/home/media/{id}")
    } else if pieces.get(3) == Some(&"listened") && method == "POST" {
        sqlx::query("INSERT INTO foreign_listen_logs(user_id,media_id) VALUES($1,$2)")
            .bind(user)
            .bind(id)
            .execute(&mut *tx)
            .await
            .map_err(internal)?;
        format!("/home/media/{id}")
    } else {
        return Err(missing());
    };
    tx.commit().await.map_err(internal)?;
    Ok(redirect)
}
#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn validates_youtube_hosts() {
        assert_eq!(
            youtube_id("https://youtu.be/dQw4w9WgXcQ").as_deref(),
            Some("dQw4w9WgXcQ")
        );
        for url in [
            "https://evil.test/watch?v=dQw4w9WgXcQ",
            "https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ",
            "http://127.0.0.1/",
            "https://youtube.com/watch?v=../x",
        ] {
            assert!(youtube_id(url).is_none());
        }
    }
}
