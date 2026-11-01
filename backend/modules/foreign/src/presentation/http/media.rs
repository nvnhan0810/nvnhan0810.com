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
    let body = bounded_response(response, 64 * 1024).await?;
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
        let id: i64 = sqlx::query_scalar(
            "INSERT INTO foreign_media(user_id,payload) VALUES($1,$2) RETURNING id",
        )
        .bind(user)
        .bind(payload)
        .fetch_one(&state.pool)
        .await
        .map_err(internal)?;
        return Ok(format!("/home/media/{id}"));
    }
    let pieces: Vec<_> = path.trim_matches('/').split('/').collect();
    let id = pieces
        .get(2)
        .and_then(|id| id.parse::<i64>().ok())
        .ok_or_else(missing)?;
    // Fetch before opening a write transaction; preserve edits if the media changed meanwhile.
    let fetched = if pieces.get(3) == Some(&"captions") && method == "POST" {
        let original = get(&state.pool, user, id).await?;
        let video = original["source_id"]
            .as_str()
            .ok_or_else(|| bad("Captions require a YouTube video"))?;
        Some((original.clone(), captions(video).await?))
    } else {
        None
    };
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
    if let Some((original, segments)) = fetched {
        let mut current = payload.clone();
        current["id"] = json!(id);
        if current != original {
            return Err(bad("Media changed during caption fetch. Reload and retry."));
        }
        if !payload["transcript"]
            .as_str()
            .unwrap_or("")
            .trim()
            .is_empty()
        {
            return Err(bad(
                "Transcript already exists. Use Edit to preserve your changes.",
            ));
        }
        payload["transcript"] = json!(
            segments
                .iter()
                .filter_map(|line| line["text"].as_str())
                .collect::<Vec<_>>()
                .join("\n")
        );
        payload["transcript_segments"] = json!(segments);
        sqlx::query(
            "UPDATE foreign_media SET payload=$3,updated_at=now() WHERE id=$1 AND user_id=$2",
        )
        .bind(id)
        .bind(user)
        .bind(payload)
        .execute(&mut *tx)
        .await
        .map_err(internal)?;
        tx.commit().await.map_err(internal)?;
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
async fn bounded_response(
    mut response: reqwest::Response,
    limit: usize,
) -> Result<Vec<u8>, ApiError> {
    if !response.status().is_success() {
        return Err(bad(
            "YouTube captions are unavailable. You can add the transcript manually.",
        ));
    }
    let mut body = Vec::new();
    while let Some(chunk) = response
        .chunk()
        .await
        .map_err(|_| bad("Could not read captions"))?
    {
        if body.len() + chunk.len() > limit {
            return Err(bad("Caption response too large"));
        }
        body.extend_from_slice(&chunk);
    }
    Ok(body)
}
async fn captions(video: &str) -> Result<Vec<Value>, ApiError> {
    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(15))
        .redirect(reqwest::redirect::Policy::none())
        .build()
        .map_err(|_| bad("Caption fetch unavailable"))?;
    let response = client
        .get("https://www.youtube.com/watch")
        .query(&[("v", video), ("hl", "en")])
        .send()
        .await
        .map_err(|_| bad("Could not fetch captions. Please retry."))?;
    let body = bounded_response(response, 4 * 1024 * 1024).await?;
    let html = std::str::from_utf8(&body).map_err(|_| bad("Invalid YouTube response"))?;
    let marker = "ytInitialPlayerResponse = ";
    let json = html
        .split_once(marker)
        .map(|(_, body)| body)
        .ok_or_else(|| bad("No captions available. Add the transcript manually."))?;
    let player = serde_json::Deserializer::from_str(json)
        .into_iter::<Value>()
        .next()
        .and_then(Result::ok)
        .ok_or_else(|| bad("Could not read YouTube captions"))?;
    let tracks = player["captions"]["playerCaptionsTracklistRenderer"]["captionTracks"]
        .as_array()
        .ok_or_else(|| bad("No captions available. Add the transcript manually."))?;
    let track = tracks
        .iter()
        .find(|track| {
            track["languageCode"]
                .as_str()
                .is_some_and(|lang| lang.starts_with("en"))
        })
        .or_else(|| tracks.first())
        .ok_or_else(|| bad("No caption track"))?;
    let mut url = reqwest::Url::parse(track["baseUrl"].as_str().unwrap_or(""))
        .map_err(|_| bad("Invalid caption URL"))?;
    if url.scheme() != "https"
        || !matches!(url.host_str(), Some("www.youtube.com" | "youtube.com"))
        || url.path() != "/api/timedtext"
    {
        return Err(bad("Unsupported caption source"));
    }
    url.query_pairs_mut().append_pair("fmt", "json3");
    let response = client
        .get(url)
        .send()
        .await
        .map_err(|_| bad("Could not fetch caption track"))?;
    let body = bounded_response(response, 2 * 1024 * 1024).await?;
    let payload: Value = serde_json::from_slice(&body)
        .map_err(|_| bad("Captions unavailable. Add the transcript manually."))?;
    let mut segments = Vec::new();
    for event in payload["events"].as_array().into_iter().flatten() {
        let Some(start) = event["tStartMs"].as_f64() else {
            continue;
        };
        let text = event["segs"]
            .as_array()
            .into_iter()
            .flatten()
            .filter_map(|segment| segment["utf8"].as_str())
            .collect::<String>();
        if !text.trim().is_empty() {
            let duration = event["dDurationMs"].as_f64().unwrap_or(2000.0).max(10.0);
            segments.push(
                json!({"start":start/1000.0,"end":(start+duration)/1000.0,"text":text.trim()}),
            );
        }
    }
    if segments.is_empty() {
        return Err(bad(
            "No subtitle text available. Add the transcript manually.",
        ));
    }
    Ok(segments)
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
