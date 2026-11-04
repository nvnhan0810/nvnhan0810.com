use serde_json::{Value, json};
fn message(value: &str) -> String {
    value.to_owned()
}
pub async fn bounded_response(
    mut response: reqwest::Response,
    limit: usize,
) -> Result<Vec<u8>, String> {
    if !response.status().is_success() {
        return Err(message(
            "YouTube captions are unavailable. You can add the transcript manually.",
        ));
    }
    let mut body = Vec::new();
    while let Some(chunk) = response
        .chunk()
        .await
        .map_err(|_| message("Could not read captions"))?
    {
        if body.len() + chunk.len() > limit {
            return Err(message("Caption response too large"));
        }
        body.extend_from_slice(&chunk);
    }
    Ok(body)
}

pub async fn captions(video: &str) -> Result<Vec<Value>, String> {
    tokio::time::timeout(std::time::Duration::from_secs(45), fetch_captions(video))
        .await
        .map_err(|_| message("YouTube caption fetch timed out. Please retry."))?
}
async fn fetch_captions(video: &str) -> Result<Vec<Value>, String> {
    let client = reqwest::Client::builder()
        .user_agent("foreign-captions/1.0")
        .timeout(std::time::Duration::from_secs(15))
        .redirect(reqwest::redirect::Policy::none())
        .build()
        .map_err(|_| message("Caption fetch unavailable"))?;
    let response = client
        .get("https://www.youtube.com/watch")
        .query(&[("v", video), ("hl", "en")])
        .send()
        .await
        .map_err(|_| message("Could not fetch captions. Please retry."))?;
    let body = bounded_response(response, 4 * 1024 * 1024).await?;
    let html = std::str::from_utf8(&body).map_err(|_| message("Invalid YouTube response"))?;
    let watch_result = match embedded_json(html, "ytInitialPlayerResponse = ") {
        Some(player) => fetch_track(&client, &player).await,
        None => Err(message("Could not read YouTube player metadata.")),
    };
    if let Ok(segments) = watch_result {
        return Ok(segments);
    }
    // A track can exist on the watch page while its signed URL returns an empty
    // body. Ask the public player endpoint for fresh track metadata before failing.
    tracing::info!(reason = %watch_result.unwrap_err(), "Trying YouTube player caption fallback");
    let key = embedded_field(html, "INNERTUBE_API_KEY")
        .and_then(|value| value.as_str().map(str::to_owned))
        .filter(|key| !key.is_empty() && key.len() <= 200)
        .ok_or_else(|| message("Could not read YouTube player configuration. Please retry."))?;
    // This is the public key advertised by the watch page, not GOOGLE_API_KEY.
    let response = client.post("https://www.youtube.com/youtubei/v1/player")
        .query(&[("key", key.as_str())])
        .json(&json!({"context":{"client":{"clientName":"ANDROID","clientVersion":"20.10.38"}},"videoId":video}))
        .send().await.map_err(|_| message("Could not refresh YouTube caption tracks. Please retry."))?;
    let body = bounded_response(response, 2 * 1024 * 1024).await?;
    let player: Value = serde_json::from_slice(&body)
        .map_err(|_| message("Could not read YouTube player response."))?;
    fetch_track(&client, &player).await
}
fn embedded_json(html: &str, marker: &str) -> Option<Value> {
    let (_, rest) = html.split_once(marker)?;
    serde_json::Deserializer::from_str(rest)
        .into_iter::<Value>()
        .next()?
        .ok()
}
fn embedded_field(html: &str, field: &str) -> Option<Value> {
    let (_, rest) = html.split_once(&format!("\"{field}\""))?;
    let rest = rest.trim_start().strip_prefix(':')?.trim_start();
    serde_json::Deserializer::from_str(rest)
        .into_iter::<Value>()
        .next()?
        .ok()
}
fn track_url(player: &Value) -> Result<reqwest::Url, String> {
    if player["playabilityStatus"]["status"] == "LOGIN_REQUIRED" {
        return Err(message(
            "YouTube requires sign-in to access this video's captions.",
        ));
    }
    let tracks = player["captions"]["playerCaptionsTracklistRenderer"]["captionTracks"]
        .as_array()
        .ok_or_else(|| message("No caption tracks are available for this video."))?;
    let english = |track: &&Value| {
        track["languageCode"]
            .as_str()
            .is_some_and(|lang| lang.starts_with("en"))
    };
    let track = tracks
        .iter()
        .filter(english)
        .find(|track| track["kind"] != "asr")
        .or_else(|| tracks.iter().find(english))
        .or_else(|| tracks.first())
        .ok_or_else(|| message("No caption tracks are available for this video."))?;
    let mut url = reqwest::Url::parse(track["baseUrl"].as_str().unwrap_or(""))
        .map_err(|_| message("Invalid caption URL"))?;
    if url.scheme() != "https"
        || !url.username().is_empty()
        || url.password().is_some()
        || url.port().is_some_and(|port| port != 443)
        || !matches!(url.host_str(), Some("www.youtube.com" | "youtube.com"))
        || url.path() != "/api/timedtext"
    {
        return Err(message("Unsupported caption source"));
    }
    // Replace fmt instead of appending duplicate format parameters.
    let pairs: Vec<_> = url
        .query_pairs()
        .filter(|(key, _)| key != "fmt")
        .map(|(key, value)| (key.into_owned(), value.into_owned()))
        .collect();
    url.query_pairs_mut()
        .clear()
        .extend_pairs(pairs)
        .append_pair("fmt", "json3");
    Ok(url)
}
async fn fetch_track(client: &reqwest::Client, player: &Value) -> Result<Vec<Value>, String> {
    let url = track_url(player)?;
    let response = client
        .get(url)
        .send()
        .await
        .map_err(|_| message("Could not fetch caption track"))?;
    let body = bounded_response(response, 2 * 1024 * 1024).await?;
    parse_segments(&body)
}
fn parse_segments(body: &[u8]) -> Result<Vec<Value>, String> {
    if body.is_empty() {
        return Err(message(
            "YouTube returned an empty response for an available caption track. Please retry.",
        ));
    }
    let payload: Value = serde_json::from_slice(body)
        .map_err(|_| message("YouTube returned an unreadable caption response. Please retry."))?;
    let mut segments = Vec::new();
    for event in payload["events"].as_array().into_iter().flatten() {
        let Some(start) = event["tStartMs"].as_f64().filter(|start| *start >= 0.0) else {
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
        return Err(message(
            "YouTube returned no subtitle text for the available track. Please retry.",
        ));
    }
    Ok(segments)
}

#[cfg(test)]
mod tests {
    use super::*;
    fn player(url: &str) -> Value {
        json!({"captions":{"playerCaptionsTracklistRenderer":{"captionTracks":[{"baseUrl":url,"languageCode":"en","kind":"asr"}]}}})
    }
    #[test]
    fn empty_caption_response_does_not_claim_the_video_has_no_track() {
        let error = parse_segments(b"").unwrap_err();
        assert!(error.contains("empty response"));
        assert!(error.contains("available caption track"));
        assert!(
            parse_segments(b"<html>error</html>")
                .unwrap_err()
                .contains("unreadable")
        );
    }
    #[test]
    fn formats_track_request_once_and_rejects_other_hosts() {
        let url = track_url(&player(
            "https://www.youtube.com/api/timedtext?v=test&fmt=srv3&sig=example",
        ))
        .unwrap();
        assert_eq!(
            url.query_pairs()
                .filter(|(key, _)| key == "fmt")
                .collect::<Vec<_>>()
                .len(),
            1
        );
        assert_eq!(
            url.query_pairs().find(|(key, _)| key == "fmt").unwrap().1,
            "json3"
        );
        for url in [
            "https://evil.test/api/timedtext",
            "http://www.youtube.com/api/timedtext",
            "https://user@www.youtube.com/api/timedtext",
            "https://www.youtube.com/watch",
        ] {
            assert!(track_url(&player(url)).is_err());
        }
    }
    #[test]
    fn parses_timed_unicode_segments_and_ignores_non_text_events() {
        let data = json!({"events":[{"tStartMs":0},{"tStartMs":1000,"dDurationMs":2500,"segs":[{"utf8":" Hello"},{"utf8":" thế giới "}]}]});
        let segments = parse_segments(&serde_json::to_vec(&data).unwrap()).unwrap();
        assert_eq!(
            segments,
            vec![json!({"start":1.0,"end":3.5,"text":"Hello thế giới"})]
        );
    }
    #[test]
    fn reads_embedded_player_fields_with_spaces_and_keeps_json_boundaries() {
        let html = r#"ytInitialPlayerResponse = {"videoDetails":{"videoId":"test"}}; window.next = {}; {"INNERTUBE_API_KEY" : "public-test-key", "other":true}"#;
        assert_eq!(
            embedded_json(html, "ytInitialPlayerResponse = ").unwrap()["videoDetails"]["videoId"],
            "test"
        );
        assert_eq!(
            embedded_field(html, "INNERTUBE_API_KEY").unwrap(),
            "public-test-key"
        );
    }
    #[tokio::test]
    #[ignore = "requires live YouTube captions; no database or user session"]
    async fn fetches_live_captions_for_reported_videos() {
        for video in ["f020SCebPLE", "B_cMErqMXdA"] {
            let segments = captions(video)
                .await
                .unwrap_or_else(|error| panic!("{video}: {error}"));
            assert!(!segments.is_empty());
            eprintln!("{video}: {} transcript segments", segments.len());
        }
    }
}
