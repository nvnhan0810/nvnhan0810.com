use super::{
    media::{bad, missing},
    routes::ForeignState,
    vocabulary::{ApiError, internal, user},
};
use crate::infrastructure::youtube::{
    Cursor, PAGE_SIZE, Position, UploadPage, VIDEO_CURSOR_VERSION, apply_durations,
    channel_selector, filter_duration, merge_pages, valid_id, videos_playlist,
};
use axum::{
    Json,
    extract::{Path, Query, State},
    http::{HeaderMap, StatusCode},
};
use serde::Deserialize;
use serde_json::{Value, json};

fn upstream(code: String) -> ApiError {
    let status = match code.as_str() {
        "youtube_not_configured" => StatusCode::SERVICE_UNAVAILABLE,
        "youtube_not_found" => StatusCode::NOT_FOUND,
        "youtube_invalid_cursor" => StatusCode::BAD_REQUEST,
        _ => StatusCode::BAD_GATEWAY,
    };
    (status, Json(json!({"error":code})))
}
async fn channels(pool: &sqlx::PgPool, owner: uuid::Uuid) -> Result<Vec<Value>, ApiError> {
    sqlx::query_scalar("SELECT jsonb_build_object('channel_id',channel_id,'title',title,'thumbnail_url',thumbnail_url,'uploads_playlist_id',uploads_playlist_id) FROM foreign_youtube_channels WHERE user_id=$1 ORDER BY title,channel_id")
        .bind(owner).fetch_all(pool).await.map_err(internal)
}
pub async fn list_channels(
    State(state): State<ForeignState>,
    headers: HeaderMap,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    Ok(Json(json!({"items":channels(&state.pool, owner).await?})))
}
#[derive(Deserialize)]
pub struct SaveChannel {
    url: String,
}
pub async fn save_channel(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Json(input): Json<SaveChannel>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    let (filter, value) = channel_selector(&input.url)
        .ok_or_else(|| bad("Use a YouTube channel URL, @handle or channel ID."))?;
    let response = state
        .youtube
        .get(
            "channels",
            &[("part", "snippet,contentDetails"), (filter, &value)],
        )
        .await
        .map_err(upstream)?;
    let channel = response["items"]
        .as_array()
        .and_then(|items| items.first())
        .ok_or_else(missing)?;
    let id = channel["id"]
        .as_str()
        .filter(|id| valid_id(id, 24))
        .ok_or_else(missing)?;
    let uploads = channel["contentDetails"]["relatedPlaylists"]["uploads"]
        .as_str()
        .ok_or_else(missing)?;
    let title = channel["snippet"]["title"]
        .as_str()
        .unwrap_or("YouTube channel");
    let thumbnail = channel["snippet"]["thumbnails"]["default"]["url"]
        .as_str()
        .unwrap_or("");
    let mut tx = state.pool.begin().await.map_err(internal)?;
    // Serialize channel mutations per owner to enforce the feed fan-out limit.
    sqlx::query("SELECT id FROM identity_users WHERE id=$1 FOR UPDATE")
        .bind(owner)
        .fetch_one(&mut *tx)
        .await
        .map_err(internal)?;
    let count: i64 = sqlx::query_scalar(
        "SELECT count(*) FROM foreign_youtube_channels WHERE user_id=$1 AND channel_id<>$2",
    )
    .bind(owner)
    .bind(id)
    .fetch_one(&mut *tx)
    .await
    .map_err(internal)?;
    if count >= 20 {
        return Err(bad(
            "You can save up to 20 channels. Remove a channel before adding another.",
        ));
    }
    sqlx::query("INSERT INTO foreign_youtube_channels(user_id,channel_id,title,thumbnail_url,uploads_playlist_id) VALUES($1,$2,$3,$4,$5) ON CONFLICT(user_id,channel_id) DO UPDATE SET title=EXCLUDED.title,thumbnail_url=EXCLUDED.thumbnail_url,uploads_playlist_id=EXCLUDED.uploads_playlist_id")
        .bind(owner).bind(id).bind(title).bind(thumbnail).bind(uploads).execute(&mut *tx).await.map_err(internal)?;
    tx.commit().await.map_err(internal)?;
    Ok(Json(json!({"channel_id":id})))
}
pub async fn remove_channel(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Path(id): Path<String>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    sqlx::query("DELETE FROM foreign_youtube_channels WHERE user_id=$1 AND channel_id=$2")
        .bind(owner)
        .bind(id)
        .execute(&state.pool)
        .await
        .map_err(internal)?;
    Ok(Json(json!({"ok":true})))
}
#[derive(Deserialize)]
pub struct VideoQuery {
    max_duration_seconds: Option<u32>,
    channel_id: Option<String>,
    cursor: Option<String>,
}
pub async fn list_videos(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Query(query): Query<VideoQuery>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    if query
        .max_duration_seconds
        .is_some_and(|seconds| !(1..=86400).contains(&seconds))
    {
        return Err(bad(
            "Maximum duration must be between 1 second and 24 hours.",
        ));
    }
    let mut channels = channels(&state.pool, owner).await?;
    if let Some(id) = &query.channel_id {
        channels.retain(|channel| channel["channel_id"].as_str() == Some(id));
        if channels.is_empty() {
            return Err(missing());
        }
    }
    let mut cursor: Cursor = match query.cursor {
        Some(cursor) if cursor.len() <= 12000 => {
            serde_json::from_str(&cursor).map_err(|_| bad("youtube_invalid_cursor"))?
        }
        Some(_) => return Err(bad("youtube_invalid_cursor")),
        None => Cursor {
            max_duration_seconds: query.max_duration_seconds,
            version: VIDEO_CURSOR_VERSION,
            channels: channels
                .iter()
                .map(|c| {
                    (
                        c["channel_id"].as_str().unwrap().into(),
                        Position::default(),
                    )
                })
                .collect(),
        },
    };
    if cursor.max_duration_seconds != query.max_duration_seconds
        || cursor.version != VIDEO_CURSOR_VERSION
        || cursor.channels.len() != channels.len()
        || channels.iter().any(|c| {
            !cursor
                .channels
                .contains_key(c["channel_id"].as_str().unwrap())
        })
        || cursor
            .channels
            .values()
            .any(|p| p.offset >= PAGE_SIZE || p.token.len() > 512)
    {
        return Err(bad("youtube_invalid_cursor"));
    }
    let mut requests = tokio::task::JoinSet::new();
    for channel in &channels {
        let id = channel["channel_id"].as_str().unwrap().to_owned();
        let position = cursor.channels[&id].clone();
        if position.done {
            continue;
        }
        let playlist = videos_playlist(&id).ok_or_else(missing)?;
        let youtube = state.youtube.clone();
        requests.spawn(async move {
            let data = youtube
                .get(
                    "playlistItems",
                    &[
                        ("part", "snippet,contentDetails"),
                        ("playlistId", &playlist),
                        ("maxResults", "24"),
                        ("pageToken", &position.token),
                    ],
                )
                .await;
            let data = match data {
                // Channels containing only Shorts may not have a Videos-tab playlist.
                Err(code) if code == "youtube_not_found" => json!({"items":[]}),
                result => result?,
            };
            Ok::<_, String>(UploadPage {
                channel_id: id,
                items: data["items"].as_array().cloned().unwrap_or_default(),
                next_token: data["nextPageToken"].as_str().unwrap_or("").into(),
            })
        });
    }
    let mut pages = Vec::new();
    while let Some(result) = requests.join_next().await {
        pages.push(
            result
                .map_err(|_| upstream("youtube_unavailable".into()))?
                .map_err(upstream)?,
        );
    }
    pages.sort_by(|a, b| a.channel_id.cmp(&b.channel_id));
    let mut items = merge_pages(&pages, &mut cursor);
    let ids: Vec<_> = items
        .iter()
        .filter_map(|item| item["video_id"].as_str().map(str::to_owned))
        .collect();
    if !ids.is_empty() {
        // Fetch durations for the whole displayed page in one request (<= 24 IDs).
        let details = state
            .youtube
            .get(
                "videos",
                &[("part", "contentDetails"), ("id", &ids.join(","))],
            )
            .await
            .map_err(upstream)?;
        apply_durations(&mut items, &details);
    }
    // The merge already advanced the cursor for skipped videos, so they will
    // not reappear on the next page. Filtering may produce a shorter/empty page.
    filter_duration(&mut items, query.max_duration_seconds);
    let saved: Vec<(String, i64)> = sqlx::query_as("SELECT payload->>'source_id',min(id) FROM foreign_media WHERE user_id=$1 AND payload->>'type'='youtube' AND payload->>'source_id'=ANY($2) GROUP BY payload->>'source_id'")
        .bind(owner).bind(ids).fetch_all(&state.pool).await.map_err(internal)?;
    for item in &mut items {
        item["media_id"] = saved
            .iter()
            .find(|(id, _)| item["video_id"].as_str() == Some(id))
            .map(|(_, id)| json!(id))
            .unwrap_or(Value::Null);
        if let Some(channel) = channels
            .iter()
            .find(|c| c["channel_id"] == item["channel_id"])
        {
            item["channel_title"] = channel["title"].clone();
        }
    }
    let next = if cursor.channels.values().all(|p| p.done) {
        None
    } else {
        Some(serde_json::to_string(&cursor).map_err(|_| bad("youtube_invalid_cursor"))?)
    };
    Ok(Json(json!({"items":items,"next_cursor":next})))
}
#[derive(Deserialize)]
pub struct AddVideo {
    difficulty: String,
}
pub async fn add_listening(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Path(video): Path<String>,
    Json(input): Json<AddVideo>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    if !valid_id(&video, 11)
        || !matches!(
            input.difficulty.as_str(),
            "beginner" | "intermediate" | "advanced"
        )
    {
        return Err(bad("Invalid video or difficulty."));
    }
    let data = state
        .youtube
        .get("videos", &[("part", "snippet,status"), ("id", &video)])
        .await
        .map_err(upstream)?;
    let item = data["items"]
        .as_array()
        .and_then(|items| items.first())
        .ok_or_else(missing)?;
    if item["status"]["privacyStatus"] != "public" {
        return Err(missing());
    }
    let channel = item["snippet"]["channelId"].as_str().ok_or_else(missing)?;
    let title = item["snippet"]["title"].as_str().unwrap_or("YouTube video");
    let payload = json!({"type":"youtube","source_id":video,"title":title,"url":format!("https://www.youtube.com/watch?v={video}"),"thumbnail_url":format!("https://i.ytimg.com/vi/{video}/hqdefault.jpg"),"channel_id":channel,"difficulty":input.difficulty,"transcript":"","transcript_segments":[]});
    let mut tx = state.pool.begin().await.map_err(internal)?;
    sqlx::query("SELECT id FROM identity_users WHERE id=$1 FOR UPDATE")
        .bind(owner)
        .fetch_one(&mut *tx)
        .await
        .map_err(internal)?;
    let exists: bool = sqlx::query_scalar(
        "SELECT EXISTS(SELECT 1 FROM foreign_youtube_channels WHERE user_id=$1 AND channel_id=$2)",
    )
    .bind(owner)
    .bind(channel)
    .fetch_one(&mut *tx)
    .await
    .map_err(internal)?;
    if !exists {
        return Err(missing());
    }
    let saved: Option<i64> = sqlx::query_scalar("SELECT min(id) FROM foreign_media WHERE user_id=$1 AND payload->>'type'='youtube' AND payload->>'source_id'=$2")
        .bind(owner).bind(&video).fetch_one(&mut *tx).await.map_err(internal)?;
    let id = if let Some(id) = saved {
        id
    } else {
        sqlx::query_scalar("INSERT INTO foreign_media(user_id,payload) VALUES($1,$2) RETURNING id")
            .bind(owner)
            .bind(payload)
            .fetch_one(&mut *tx)
            .await
            .map_err(internal)?
    };
    tx.commit().await.map_err(internal)?;
    // Creation succeeded even if queue dispatch is temporarily unavailable. Retry is idempotent.
    let queued = crate::infrastructure::transcript_queue::enqueue(
        &state.pool,
        &state.transcript_queue,
        id,
        state.transcript_queue.dispatch.automatic,
    )
    .await
    .is_ok();
    Ok(Json(json!({"media_id":id,"captions_queued":queued})))
}
