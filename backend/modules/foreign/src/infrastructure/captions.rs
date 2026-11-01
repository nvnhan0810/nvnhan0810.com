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
    let client = reqwest::Client::builder()
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
    let marker = "ytInitialPlayerResponse = ";
    let json = html
        .split_once(marker)
        .map(|(_, body)| body)
        .ok_or_else(|| message("No captions available. Add the transcript manually."))?;
    let player = serde_json::Deserializer::from_str(json)
        .into_iter::<Value>()
        .next()
        .and_then(Result::ok)
        .ok_or_else(|| message("Could not read YouTube captions"))?;
    let tracks = player["captions"]["playerCaptionsTracklistRenderer"]["captionTracks"]
        .as_array()
        .ok_or_else(|| message("No captions available. Add the transcript manually."))?;
    let track = tracks
        .iter()
        .find(|track| {
            track["languageCode"]
                .as_str()
                .is_some_and(|lang| lang.starts_with("en"))
        })
        .or_else(|| tracks.first())
        .ok_or_else(|| message("No caption track"))?;
    let mut url = reqwest::Url::parse(track["baseUrl"].as_str().unwrap_or(""))
        .map_err(|_| message("Invalid caption URL"))?;
    if url.scheme() != "https"
        || !matches!(url.host_str(), Some("www.youtube.com" | "youtube.com"))
        || url.path() != "/api/timedtext"
    {
        return Err(message("Unsupported caption source"));
    }
    url.query_pairs_mut().append_pair("fmt", "json3");
    let response = client
        .get(url)
        .send()
        .await
        .map_err(|_| message("Could not fetch caption track"))?;
    let body = bounded_response(response, 2 * 1024 * 1024).await?;
    let payload: Value = serde_json::from_slice(&body)
        .map_err(|_| message("Captions unavailable. Add the transcript manually."))?;
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
        return Err(message(
            "No subtitle text available. Add the transcript manually.",
        ));
    }
    Ok(segments)
}
