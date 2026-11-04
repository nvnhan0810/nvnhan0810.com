//! Public channel catalog. Only fixed Google API endpoints receive the server key.
use serde::{Deserialize, Serialize};
use serde_json::Value;

pub const PAGE_SIZE: usize = 24;
pub const VIDEO_CURSOR_VERSION: u8 = 1;

/// YouTube's Videos-tab playlist excludes Shorts and live streams.
/// Derive it at read time so existing saved channels also use video-only results.
/// UULF is an upstream convention, not a documented Data API contract.
pub fn videos_playlist(channel_id: &str) -> Option<String> {
    (valid_id(channel_id, 24) && channel_id.starts_with("UC"))
        .then(|| format!("UULF{}", &channel_id[2..]))
}

#[derive(Clone)]
pub struct Youtube {
    client: reqwest::Client,
    key: Option<String>,
}
impl Youtube {
    pub fn new(key: Option<String>) -> Result<Self, String> {
        Ok(Self {
            client: reqwest::Client::builder()
                .timeout(std::time::Duration::from_secs(8))
                .redirect(reqwest::redirect::Policy::none())
                .build()
                .map_err(|_| "youtube_unavailable".to_owned())?,
            key: key
                .map(|key| key.trim().to_owned())
                .filter(|key| !key.is_empty()),
        })
    }
    pub async fn get(&self, resource: &str, params: &[(&str, &str)]) -> Result<Value, String> {
        let key = self.key.as_deref().ok_or("youtube_not_configured")?;
        let response = self
            .client
            .get(format!("https://www.googleapis.com/youtube/v3/{resource}"))
            .query(params)
            .query(&[("key", key)])
            .send()
            .await
            .map_err(|error| {
                // Do not log the reqwest error/URL: the query contains the API key.
                tracing::warn!(
                    resource,
                    timeout = error.is_timeout(),
                    connect = error.is_connect(),
                    "YouTube request failed"
                );
                if error.is_timeout() {
                    "youtube_timeout"
                } else {
                    "youtube_connection_failed"
                }
            })?;
        let status = response.status();
        let body = super::captions::bounded_response(response, 2 * 1024 * 1024)
            .await
            .map_err(|_| "youtube_unavailable")?;
        let value: Value = serde_json::from_slice(&body).map_err(|_| "youtube_unavailable")?;
        if !status.is_success() {
            let reason = value["error"]["errors"][0]["reason"].as_str().unwrap_or("");
            let detail = value["error"]["details"]
                .as_array()
                .and_then(|details| details.iter().find_map(|entry| entry["reason"].as_str()))
                .unwrap_or("");
            tracing::warn!(
                resource,
                status = status.as_u16(),
                reason,
                detail,
                "YouTube API rejected request"
            );
            return Err(api_error(&value).into());
        }
        Ok(value)
    }
}

/// Match by video ID because Google can reorder or omit unavailable video details.
pub fn apply_durations(items: &mut [Value], details: &Value) {
    for item in items {
        item["duration"] = details["items"]
            .as_array()
            .and_then(|videos| videos.iter().find(|video| video["id"] == item["video_id"]))
            .and_then(|video| video["contentDetails"]["duration"].as_str())
            .map(|duration| serde_json::json!(duration))
            .unwrap_or(Value::Null);
    }
}

/// Parse YouTube's day/hour/minute/second ISO 8601 duration without guessing
/// unknown values. Checked arithmetic rejects malformed or overflowing input.
pub fn duration_seconds(duration: &str) -> Option<u64> {
    let body = duration.strip_prefix('P')?;
    let (date, time) = match body.split_once('T') {
        Some((date, time)) if !time.is_empty() => (date, Some(time)),
        Some(_) => return None,
        None => (body, None),
    };
    let mut total = 0u64;
    let mut seen = false;
    if !date.is_empty() {
        let days = date.strip_suffix('D')?;
        if days.is_empty() || !days.bytes().all(|c| c.is_ascii_digit()) {
            return None;
        }
        total = days.parse::<u64>().ok()?.checked_mul(86400)?;
        seen = true;
    }
    let mut number = 0u64;
    let mut digits = false;
    let mut previous_rank = 4;
    for byte in time.unwrap_or("").bytes() {
        if byte.is_ascii_digit() {
            number = number.checked_mul(10)?.checked_add((byte - b'0') as u64)?;
            digits = true;
            continue;
        }
        let (rank, factor) = match byte {
            b'H' => (3, 3600),
            b'M' => (2, 60),
            b'S' => (1, 1),
            _ => return None,
        };
        if !digits || rank >= previous_rank {
            return None;
        }
        total = total.checked_add(number.checked_mul(factor)?)?;
        previous_rank = rank;
        number = 0;
        digits = false;
        seen = true;
    }
    if !seen || digits { None } else { Some(total) }
}

pub fn filter_duration(items: &mut Vec<Value>, maximum: Option<u32>) {
    if let Some(maximum) = maximum {
        items.retain(|item| {
            item["duration"]
                .as_str()
                .and_then(duration_seconds)
                .is_some_and(|seconds| seconds <= u64::from(maximum))
        });
    }
}

fn api_error(value: &Value) -> &'static str {
    let reasons: Vec<_> = ["errors", "details"]
        .into_iter()
        .filter_map(|key| value["error"][key].as_array())
        .flatten()
        .filter_map(|entry| entry["reason"].as_str())
        .collect();
    let has = |options: &[&str]| reasons.iter().any(|reason| options.contains(reason));
    if has(&[
        "quotaExceeded",
        "dailyLimitExceeded",
        "QUOTA_EXCEEDED",
        "RATE_LIMIT_EXCEEDED",
    ]) {
        "youtube_quota_exceeded"
    } else if has(&["keyInvalid", "API_KEY_INVALID", "API_KEY_EXPIRED"]) {
        "youtube_invalid_key"
    } else if has(&[
        "accessNotConfigured",
        "SERVICE_DISABLED",
        "API_KEY_SERVICE_BLOCKED",
    ]) {
        "youtube_api_disabled"
    } else if has(&[
        "ipRefererBlocked",
        "API_KEY_HTTP_REFERRER_BLOCKED",
        "API_KEY_IP_ADDRESS_BLOCKED",
        "API_KEY_ANDROID_APP_BLOCKED",
        "API_KEY_IOS_APP_BLOCKED",
    ]) {
        "youtube_key_restricted"
    } else if has(&["invalidPageToken"]) {
        "youtube_invalid_cursor"
    } else if has(&["playlistNotFound", "channelNotFound", "videoNotFound"]) {
        "youtube_not_found"
    } else if has(&["forbidden"]) || value["error"]["code"] == 403 {
        "youtube_access_denied"
    } else {
        "youtube_unavailable"
    }
}

pub fn valid_id(id: &str, length: usize) -> bool {
    id.len() == length
        && id
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b == b'_' || b == b'-')
}
/// Resolve channel IDs, @handles and canonical channel/user URLs without fetching user URLs.
pub fn channel_selector(input: &str) -> Option<(&'static str, String)> {
    let input = input.trim();
    if valid_id(input, 24) && input.starts_with("UC") {
        return Some(("id", input.into()));
    }
    if let Some(handle) = input.strip_prefix('@') {
        return valid_handle(handle).then(|| ("forHandle", input.into()));
    }
    let url = reqwest::Url::parse(input).ok()?;
    if url.scheme() != "https"
        || !url.username().is_empty()
        || url.password().is_some()
        || !matches!(
            url.host_str()?,
            "youtube.com" | "www.youtube.com" | "m.youtube.com"
        )
    {
        return None;
    }
    let parts: Vec<_> = url.path().trim_matches('/').split('/').collect();
    match parts.as_slice() {
        ["channel", id] if id.starts_with("UC") && valid_id(id, 24) => Some(("id", (*id).into())),
        [handle] | [handle, "videos" | "shorts" | "featured"] if handle.starts_with('@') => {
            channel_selector(handle)
        }
        ["user", name] if valid_handle(name) => Some(("forUsername", (*name).into())),
        _ => None,
    }
}
fn valid_handle(handle: &str) -> bool {
    !handle.is_empty()
        && handle.len() <= 100
        && handle
            .chars()
            .all(|c| c.is_alphanumeric() || matches!(c, '_' | '-' | '.'))
}

#[derive(Clone, Default, Debug, Serialize, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct Position {
    pub token: String,
    pub offset: usize,
    pub done: bool,
}
#[derive(Clone, Default, Debug, Serialize, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct Cursor {
    #[serde(default)]
    pub max_duration_seconds: Option<u32>,
    #[serde(default)]
    pub version: u8,
    pub channels: std::collections::BTreeMap<String, Position>,
}
#[derive(Debug)]
pub struct UploadPage {
    pub channel_id: String,
    pub items: Vec<Value>,
    pub next_token: String,
}
/// Merge ordered channel pages while retaining each channel's unconsumed position.
/// Page tokens alone would discard videos from channels that did not fill this page.
pub fn merge_pages(pages: &[UploadPage], cursor: &mut Cursor) -> Vec<Value> {
    let mut items = Vec::new();
    // Pick only the next item from each channel: published dates can differ from
    // playlist order (premieres), so sorting the entire batch could skip entries.
    for _ in 0..PAGE_SIZE {
        let next = pages
            .iter()
            .enumerate()
            .filter_map(|(index, page)| {
                let offset = cursor.channels[&page.channel_id].offset;
                page.items.get(offset).map(|item| (index, item))
            })
            .max_by(|a, b| {
                let date = |value: &Value| {
                    value["snippet"]["publishedAt"]
                        .as_str()
                        .unwrap_or("")
                        .to_owned()
                };
                date(a.1).cmp(&date(b.1)).then_with(|| b.0.cmp(&a.0))
            });
        let Some((index, item)) = next else {
            break;
        };
        cursor
            .channels
            .get_mut(&pages[index].channel_id)
            .unwrap()
            .offset += 1;
        let id = item["contentDetails"]["videoId"].as_str().unwrap_or("");
        if valid_id(id, 11)
            && item["snippet"]["title"] != "Private video"
            && item["snippet"]["title"] != "Deleted video"
        {
            items.push(serde_json::json!({
                "video_id": id, "title": item["snippet"]["title"],
                "channel_id": pages[index].channel_id,
                "channel_title": item["snippet"]["videoOwnerChannelTitle"],
                "published_at": item["contentDetails"]["videoPublishedAt"],
                "thumbnail_url": format!("https://i.ytimg.com/vi/{id}/hqdefault.jpg"),
            }));
        }
    }
    for page in pages {
        let position = cursor.channels.get_mut(&page.channel_id).unwrap();
        if position.offset >= page.items.len() {
            position.offset = 0;
            position.token = page.next_token.clone();
            position.done = position.token.is_empty();
        }
    }
    items
}

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;
    #[test]
    fn duration_parser_handles_units_and_rejects_bad_values() {
        for (input, expected) in [
            ("PT12M34S", 754),
            ("PT1H2M3S", 3723),
            ("P1DT2H", 93600),
            ("PT0S", 0),
        ] {
            assert_eq!(duration_seconds(input), Some(expected));
        }
        for input in [
            "",
            "P",
            "PT",
            "PT1M1H",
            "PT1M2M",
            "PT-1S",
            "PT1",
            "P1Y",
            "PT999999999999999999999H",
        ] {
            assert_eq!(duration_seconds(input), None, "{input}");
        }
    }
    #[test]
    fn enabled_duration_filter_includes_boundary_and_skips_long_or_unknown_videos() {
        let source = vec![
            json!({"duration":"PT9M59S"}),
            json!({"duration":"PT10M"}),
            json!({"duration":"PT10M1S"}),
            json!({"duration":null}),
        ];
        let mut filtered = source.clone();
        filter_duration(&mut filtered, Some(600));
        assert_eq!(filtered, source[..2]);
        filter_duration(&mut filtered, None);
        assert_eq!(filtered.len(), 2);
        let mut unfiltered = source.clone();
        filter_duration(&mut unfiltered, None);
        assert_eq!(unfiltered, source);
    }
    #[test]
    fn matches_durations_by_id_and_keeps_missing_values_unknown() {
        let mut items = vec![
            json!({"video_id":"first"}),
            json!({"video_id":"missing"}),
            json!({"video_id":"last"}),
        ];
        apply_durations(
            &mut items,
            &json!({"items":[
                {"id":"last","contentDetails":{"duration":"PT1H2M3S"}},
                {"id":"first","contentDetails":{"duration":"PT12M34S"}}
            ]}),
        );
        assert_eq!(items[0]["duration"], "PT12M34S");
        assert!(items[1]["duration"].is_null());
        assert_eq!(items[2]["duration"], "PT1H2M3S");
    }
    #[test]
    fn uses_videos_tab_instead_of_mixed_uploads() {
        assert_eq!(
            videos_playlist("UC_x5XG1OV2P6uZZ5FSM9Ttw").as_deref(),
            Some("UULF_x5XG1OV2P6uZZ5FSM9Ttw")
        );
        assert!(videos_playlist("UU_x5XG1OV2P6uZZ5FSM9Ttw").is_none());
        assert!(videos_playlist("UCinvalid").is_none());
    }
    #[test]
    fn old_mixed_upload_cursors_have_a_different_version() {
        let old: Cursor = serde_json::from_str(r#"{"channels":{}}"#).unwrap();
        assert_ne!(old.version, VIDEO_CURSOR_VERSION);
        let cursor = Cursor {
            version: VIDEO_CURSOR_VERSION,
            ..Cursor::default()
        };
        let restored: Cursor =
            serde_json::from_str(&serde_json::to_string(&cursor).unwrap()).unwrap();
        assert_eq!(restored.version, VIDEO_CURSOR_VERSION);
    }
    #[test]
    fn distinguishes_key_and_service_errors_from_connection_failures() {
        for (reason, expected) in [
            ("API_KEY_HTTP_REFERRER_BLOCKED", "youtube_key_restricted"),
            ("API_KEY_IP_ADDRESS_BLOCKED", "youtube_key_restricted"),
            ("SERVICE_DISABLED", "youtube_api_disabled"),
            ("API_KEY_INVALID", "youtube_invalid_key"),
            ("quotaExceeded", "youtube_quota_exceeded"),
            ("invalidPageToken", "youtube_invalid_cursor"),
        ] {
            assert_eq!(
                api_error(
                    &json!({"error":{"code":403,"errors":[{"reason":"forbidden"}],"details":[{"reason":reason}]}})
                ),
                expected
            );
        }
        assert_eq!(
            api_error(&json!({"error":{"errors":[{"reason":"keyInvalid"}]}})),
            "youtube_invalid_key"
        );
    }
    #[tokio::test]
    async fn missing_key_is_reported_without_a_network_call() {
        assert_eq!(
            Youtube::new(None)
                .unwrap()
                .get("channels", &[])
                .await
                .unwrap_err(),
            "youtube_not_configured"
        );
    }
    #[test]
    fn consumed_pages_end_and_invalid_entries_do_not_block_pagination() {
        let pages = vec![UploadPage {
            channel_id: "channel".into(),
            next_token: String::new(),
            items: vec![
                json!({"contentDetails":{"videoId":"dQw4w9WgXcQ"},"snippet":{"title":"Private video"}}),
                json!({"contentDetails":{"videoId":"abcdefghijk"},"snippet":{"title":"Lesson"}}),
            ],
        }];
        let mut cursor = Cursor::default();
        cursor
            .channels
            .insert("channel".into(), Position::default());
        let items = merge_pages(&pages, &mut cursor);
        assert_eq!(items.len(), 1);
        assert_eq!(items[0]["video_id"], "abcdefghijk");
        assert!(cursor.channels["channel"].done);
    }
    #[test]
    fn channel_inputs_are_restricted() {
        for input in [
            "@BBClearningEnglish",
            "https://www.youtube.com/@BBClearningEnglish/videos",
            "https://youtube.com/channel/UC1234567890123456789012",
        ] {
            assert!(channel_selector(input).is_some(), "{input}");
        }
        for input in [
            "https://evil.test/@bbc",
            "http://youtube.com/@bbc",
            "https://youtube.com.evil.test/@bbc",
            "https://user@youtube.com/@bbc",
            "https://youtube.com/watch?v=abc",
            "@../bad",
        ] {
            assert!(channel_selector(input).is_none(), "{input}");
        }
    }
    #[test]
    fn merged_pagination_does_not_skip_other_channels() {
        let page = |id: &str, month: &str| {
            UploadPage {
            channel_id: id.into(), next_token: "older".into(),
            items: (0..24).map(|i| json!({"contentDetails":{"videoId":format!("video{i:06}"), "videoPublishedAt":format!("2026-{month}-{:02}",24-i)}, "snippet":{"title":"Video", "publishedAt":format!("2026-{month}-{:02}",24-i)}})).collect(),
        }
        };
        let pages = vec![page("new", "11"), page("old", "10")];
        let mut cursor = Cursor::default();
        for page in &pages {
            cursor
                .channels
                .insert(page.channel_id.clone(), Position::default());
        }
        assert_eq!(merge_pages(&pages, &mut cursor).len(), 24);
        assert_eq!(cursor.channels["new"].token, "older");
        assert_eq!(cursor.channels["old"].offset, 0);
        assert_eq!(cursor.channels["old"].token, "");
        let older = [page("old", "10")];
        assert_eq!(merge_pages(&older, &mut cursor).len(), 24);
        assert_eq!(cursor.channels["old"].token, "older");
    }
}
