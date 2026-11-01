//! JSON page adapter for the source Vue screens, authenticated by Identity.
use super::{
    media::{bad, missing},
    routes::ForeignState,
    vocabulary::{ApiError, internal, user},
};
use crate::{
    domain::{
        dictionary::{Entry, normalize_word},
        games,
    },
    infrastructure::vocabulary,
};
use axum::{
    Json,
    extract::{Query, State},
    http::HeaderMap,
};
use serde::Deserialize;
use serde_json::{Value, json};
use uuid::Uuid;
#[derive(Deserialize)]
pub struct PageQuery {
    path: String,
}
#[derive(Deserialize)]
pub struct Action {
    path: String,
    #[serde(default)]
    data: Value,
    method: String,
    #[serde(default)]
    round_id: Option<String>,
}
fn parts(path: &str) -> Vec<&str> {
    path.trim_matches('/').split('/').collect()
}
fn mode(path: &str) -> Option<&str> {
    if path.starts_with("/home/quiz/") {
        Some("quiz")
    } else {
        let mode = path.strip_prefix("/home/puzzle/")?.split('/').next()?;
        ["scramble", "wordle", "hangman", "word-search"]
            .contains(&mode)
            .then_some(mode)
    }
}
pub async fn get(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Query(query): Query<PageQuery>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    page(&state, owner, &query.path).await.map(Json)
}
async fn vocab(pool: &sqlx::PgPool, owner: Uuid, id: i64) -> Result<Value, ApiError> {
    let row:Option<(i64,Value)>=sqlx::query_as("SELECT v.id,COALESCE(v.entry_override,e.payload) FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 AND v.id=$2").bind(owner).bind(id).fetch_optional(pool).await.map_err(internal)?;
    let (id, entry) = row.ok_or_else(missing)?;
    let mut item = games::reveal(&entry);
    item["id"] = json!(id);
    item["examples"] = json!([]);
    Ok(item)
}
async fn page(state: &ForeignState, owner: Uuid, path: &str) -> Result<Value, ApiError> {
    if path.len() > 2000 {
        return Err(bad("Path too long"));
    }
    let url =
        reqwest::Url::parse(&format!("http://local{path}")).map_err(|_| bad("Invalid path"))?;
    let path = url.path();
    let segments = parts(path);
    match path {
        "/home/vocab" => {
            let rows:Vec<(i64,Value)>=sqlx::query_as("SELECT v.id,COALESCE(v.entry_override,e.payload) FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 ORDER BY v.id DESC").bind(owner).fetch_all(&state.pool).await.map_err(internal)?;
            let items: Vec<_> = rows
                .into_iter()
                .map(|(id, entry)| {
                    let mut value = games::reveal(&entry);
                    value["id"] = json!(id);
                    value["examples"] = json!([]);
                    value
                })
                .collect();
            Ok(json!({"items":items}))
        }
        "/home/lookup" => {
            let q = url
                .query_pairs()
                .find(|(key, _)| key == "q")
                .map(|(_, value)| value.into_owned())
                .unwrap_or_default();
            if q.is_empty() {
                return Ok(
                    json!({"queryWord":"","embed":url.query_pairs().any(|(key,value)|key=="embed" && (value=="1" || value=="true"))}),
                );
            }
            let mut result = lookup(state, owner, &q).await?;
            result["embed"] = json!(
                url.query_pairs()
                    .any(|(key, value)| key == "embed" && (value == "1" || value == "true"))
            );
            Ok(result)
        }
        "/home/quiz" | "/home/puzzle" => {
            sqlx::query("DELETE FROM foreign_game_sessions WHERE user_id=$1")
                .bind(owner)
                .execute(&state.pool)
                .await
                .map_err(internal)?;
            Ok(json!({}))
        }
        "/home/media" => {
            let items:Vec<Value>=sqlx::query_scalar("SELECT payload || jsonb_build_object('id',id) FROM foreign_media WHERE user_id=$1 ORDER BY id DESC").bind(owner).fetch_all(&state.pool).await.map_err(internal)?;
            Ok(json!({"items":items}))
        }
        "/home/profile" => {
            let user:Value=sqlx::query_scalar("SELECT jsonb_build_object('id',id,'name',name,'email',email) FROM identity_users WHERE id=$1").bind(owner).fetch_one(&state.pool).await.map_err(internal)?;
            let stats:Value=sqlx::query_scalar("SELECT jsonb_build_object('vocabulary_count',(SELECT count(*) FROM foreign_vocabularies WHERE user_id=$1),'media_count',(SELECT count(*) FROM foreign_media WHERE user_id=$1),'average_score_percent',(SELECT round(avg(score::numeric/NULLIF(total,0))*100) FROM foreign_attempts WHERE user_id=$1))").bind(owner).fetch_one(&state.pool).await.map_err(internal)?;
            let history:Vec<Value>=sqlx::query_scalar("SELECT jsonb_build_object('id',id::text,'kind',kind,'title',initcap(kind),'type',kind,'score',score,'total',total,'completed_at',completed_at) FROM foreign_attempts WHERE user_id=$1 ORDER BY completed_at DESC LIMIT 100").bind(owner).fetch_all(&state.pool).await.map_err(internal)?;
            Ok(json!({"user":user,"stats":stats,"history":history}))
        }
        _ if path.starts_with("/home/word/") => {
            let word = url.path().trim_start_matches("/home/word/");
            let word = percent_decode(word);
            let id: Option<i64> = sqlx::query_scalar("SELECT v.id FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 AND e.word=lower($2)").bind(owner).bind(&word).fetch_optional(&state.pool).await.map_err(internal)?;
            let detail = url
                .query_pairs()
                .any(|(key, value)| key == "detail" && value == "1");
            let redirect = if detail && let Some(id) = id {
                format!("/home/vocab/{id}")
            } else {
                let mut target =
                    reqwest::Url::parse("http://local/home/lookup").expect("static URL");
                target.query_pairs_mut().append_pair("q", &word);
                format!("{}?{}", target.path(), target.query().unwrap_or(""))
            };
            Ok(json!({"redirect":redirect}))
        }
        _ if path.starts_with("/home/vocab/") && segments.len() == 3 => {
            let id = segments[2].parse().map_err(|_| missing())?;
            Ok(json!({"vocab":vocab(&state.pool,owner,id).await?}))
        }
        _ if path.starts_with("/home/media/") => {
            let id = segments
                .get(2)
                .and_then(|id| id.parse().ok())
                .ok_or_else(missing)?;
            Ok(json!({"item":super::media::get(&state.pool,owner,id).await?}))
        }
        _ if mode(path).is_some() => {
            let mode = mode(path).ok_or_else(missing)?;
            let stored: Option<Value> = sqlx::query_scalar(
                "SELECT state FROM foreign_game_sessions WHERE user_id=$1 AND mode=$2",
            )
            .bind(owner)
            .bind(mode)
            .fetch_optional(&state.pool)
            .await
            .map_err(internal)?;
            if let Some(stored) = stored {
                let best: i32 = sqlx::query_scalar(
                    "SELECT best_correct FROM foreign_game_records WHERE user_id=$1 AND game=$2",
                )
                .bind(owner)
                .bind(mode)
                .fetch_optional(&state.pool)
                .await
                .map_err(internal)?
                .unwrap_or(0);
                Ok(games::public(&stored, i64::from(best)))
            } else {
                Ok(
                    json!({"autostart":mode=="quiz" && url.query_pairs().any(|(k,v)|k=="autostart" && v=="1")}),
                )
            }
        }
        _ => Err(missing()),
    }
}
fn percent_decode(word: &str) -> String {
    reqwest::Url::parse(&format!("http://local/?q={}", word.replace('+', "%2B")))
        .ok()
        .and_then(|url| {
            url.query_pairs()
                .next()
                .map(|(_, value)| value.into_owned())
        })
        .unwrap_or_else(|| word.to_owned())
}
async fn lookup(state: &ForeignState, owner: Uuid, word: &str) -> Result<Value, ApiError> {
    let word = normalize_word(word).ok_or_else(|| bad("Invalid word"))?;
    let local: Option<Value> =
        sqlx::query_scalar("SELECT COALESCE((SELECT v.entry_override FROM foreign_vocabularies v WHERE v.dictionary_entry_id=e.id AND v.user_id=$2),e.payload) FROM foreign_dictionary_entries e WHERE word=$1")
            .bind(&word)
            .bind(owner)
            .fetch_optional(&state.pool)
            .await
            .map_err(internal)?;
    let result = if let Some(local) = local {
        local
    } else {
        match state.dictionary.lookup(&word).await {
            Ok(entries) => {
                let mut entries = entries.into_iter();
                let Some(mut first) = entries.next() else {
                    return Err(bad("No dictionary result"));
                };
                for entry in entries {
                    first.meanings.extend(entry.meanings);
                    first.phonetics.extend(entry.phonetics);
                }
                serde_json::to_value(first).map_err(|_| bad("Invalid dictionary result"))?
            }
            Err(crate::infrastructure::dictionary::LookupError::NotFound) => {
                json!({"word":word,"phonetic":"","meanings":[]})
            }
            Err(_) => return Err(bad("Dictionary unavailable. Please retry.")),
        }
    };
    // Preserve the source app's dictionary cache and pronunciation across a subsequent JSON save.
    if result["meanings"]
        .as_array()
        .is_some_and(|meanings| !meanings.is_empty())
    {
        sqlx::query("INSERT INTO foreign_dictionary_entries(word,payload) VALUES($1,$2) ON CONFLICT(word) DO NOTHING").bind(&word).bind(&result).execute(&state.pool).await.map_err(internal)?;
    }
    let audio = result["phonetics"]
        .as_array()
        .into_iter()
        .flatten()
        .find_map(|phonetic| phonetic["audio"].as_str().filter(|audio| !audio.is_empty()))
        .map(|audio| json!(audio))
        .unwrap_or(Value::Null);
    let mut result = games::reveal(&result);
    result["audio_url"] = audio;
    let saved:Option<i64>=sqlx::query_scalar("SELECT v.id FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 AND e.word=$2").bind(owner).bind(&word).fetch_optional(&state.pool).await.map_err(internal)?;
    let draft = result["meanings"].as_array().is_none_or(Vec::is_empty);
    Ok(
        json!({"word":word,"queryWord":word,"result":result,"draft":draft,"saved":saved.is_some(),"savedVocabularyId":saved}),
    )
}
pub async fn action(
    State(state): State<ForeignState>,
    headers: HeaderMap,
    Json(input): Json<Action>,
) -> Result<Json<Value>, ApiError> {
    let owner = user(&state, &headers).await?;
    let path = input.path.as_str();
    let data = &input.data;
    if (path == "/home/lookup" || path == "/lookup") && input.method == "POST" {
        let mut result = lookup(&state, owner, data["word"].as_str().unwrap_or("")).await?;
        result["embed"] = json!(data["embed"].as_bool().unwrap_or(false));
        return Ok(Json(json!({"props":result})));
    }
    if (path == "/home/lookup/save" || path == "/lookup/save") && input.method == "POST" {
        let meanings:Vec<_>=data["meanings"].as_array().into_iter().flatten().map(|m|json!({"partOfSpeech":m["part_of_speech"].as_str().unwrap_or(""),"synonyms":m["synonyms"].as_array().cloned().unwrap_or_default(),"antonyms":m["antonyms"].as_array().cloned().unwrap_or_default(),"definitions":[{"definition":m["definition"].as_str().unwrap_or(""),"example":m["example"].as_str().unwrap_or(""),"examples":m["examples"].as_array().cloned().unwrap_or_default()}]})).collect();
        let mut entry: Entry = serde_json::from_value(
            json!({"word":data["word"],"phonetic":data["phonetic"],"meanings":meanings}),
        )
        .map_err(|_| bad("Invalid word"))?;
        crate::application::vocabulary::validate(&mut entry).map_err(bad)?;
        let existing: Option<Value> =
            sqlx::query_scalar("SELECT payload FROM foreign_dictionary_entries WHERE word=$1")
                .bind(&entry.word)
                .fetch_optional(&state.pool)
                .await
                .map_err(internal)?;
        if let Some(existing) = existing {
            entry.phonetics =
                serde_json::from_value(existing["phonetics"].clone()).unwrap_or_default();
        }

        let id = vocabulary::save(&state.pool, owner, &entry)
            .await
            .map_err(internal)?;
        sqlx::query("UPDATE foreign_vocabularies SET entry_override=$3,updated_at=now() WHERE id=$1 AND user_id=$2").bind(id).bind(owner).bind(serde_json::to_value(&entry).map_err(|_|bad("Invalid entry"))?).execute(&state.pool).await.map_err(internal)?;
        let mut result = lookup(&state, owner, &entry.word).await?;
        result["embed"] = json!(data["embed"].as_bool().unwrap_or(false));
        return Ok(Json(json!({"props":result})));
    }
    if path.starts_with("/home/vocab/") && input.method == "DELETE" {
        let id: i64 = path
            .trim_start_matches("/home/vocab/")
            .parse()
            .map_err(|_| missing())?;
        let result = sqlx::query("DELETE FROM foreign_vocabularies WHERE id=$1 AND user_id=$2")
            .bind(id)
            .bind(owner)
            .execute(&state.pool)
            .await
            .map_err(internal)?;
        if result.rows_affected() != 1 {
            return Err(missing());
        }
        return Ok(Json(json!({"redirect":"/home/vocab"})));
    }
    if path.starts_with("/home/media/") {
        let redirect = super::media::action(&state, owner, path, data, &input.method).await?;
        return Ok(Json(json!({"redirect":redirect})));
    }
    if path == "/home/puzzle/exit" {
        sqlx::query("DELETE FROM foreign_game_sessions WHERE user_id=$1")
            .bind(owner)
            .execute(&state.pool)
            .await
            .map_err(internal)?;
        return Ok(Json(json!({"redirect":"/home/quiz"})));
    }
    if input.method != "POST" {
        return Err(bad("Unsupported method"));
    }
    let mode = mode(path).ok_or_else(missing)?;
    let action = path.rsplit('/').next().ok_or_else(missing)?;
    let mut tx = state.pool.begin().await.map_err(internal)?;
    // One per-user advisory transaction lock serializes all game transitions, including first round creation.
    sqlx::query("SELECT pg_advisory_xact_lock(hashtextextended($1, 31003101))")
        .bind(owner.to_string())
        .execute(&mut *tx)
        .await
        .map_err(internal)?;
    let previous: Option<Value> = sqlx::query_scalar(
        "SELECT state FROM foreign_game_sessions WHERE user_id=$1 AND mode=$2 FOR UPDATE",
    )
    .bind(owner)
    .bind(mode)
    .fetch_optional(&mut *tx)
    .await
    .map_err(internal)?;
    if previous
        .as_ref()
        .and_then(|round| round["roundId"].as_str())
        != input.round_id.as_deref()
    {
        return Err((
            axum::http::StatusCode::CONFLICT,
            Json(json!({"error":"This round changed. Reload before continuing."})),
        ));
    }
    let mut stored = if action == "next" {
        let rows:Vec<(i64,Value)>=sqlx::query_as("SELECT v.id,COALESCE(v.entry_override,e.payload) FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 AND jsonb_array_length(COALESCE(v.entry_override,e.payload)->'meanings')>0 AND ($2='quiz' OR (e.word ~ '^[a-z]+$' AND length(e.word) BETWEEN (CASE WHEN $2='wordle' THEN 5 ELSE 3 END) AND (CASE WHEN $2='wordle' THEN 5 WHEN $2='word-search' THEN 8 ELSE 12 END))) ORDER BY CASE WHEN $2='quiz' THEN -ln(GREATEST(random(),0.000001)) / GREATEST(0.01, LEAST(1.0,GREATEST(0.15,COALESCE(extract(epoch FROM (now()-v.last_quizzed_at))/86400,1.0)))/(v.times_quizzed+1)) ELSE random() END LIMIT 32").bind(owner).bind(mode).fetch_all(&mut *tx).await.map_err(internal)?;
        let score = previous
            .as_ref()
            .and_then(|s| s["score"].as_i64())
            .unwrap_or(0);
        let mut next = games::start(mode, &rows, score).map_err(bad)?;
        if let Some(started) = previous
            .as_ref()
            .and_then(|round| round["startedAt"].as_u64())
        {
            next["startedAt"] = json!(started);
        }
        next
    } else {
        previous.ok_or_else(|| bad("Start a round first."))?
    };
    let finished = if action == "next" {
        false
    } else {
        games::act(&mut stored, action, data).map_err(bad)?
    };
    if finished {
        let correct = stored["wasCorrect"].as_bool().unwrap_or(false);
        let id = stored["id"].as_i64();
        sqlx::query("INSERT INTO foreign_attempts(user_id,vocabulary_id,kind,score,total) VALUES($1,$2,$3,$4,1)").bind(owner).bind(id).bind(mode).bind(i32::from(correct)).execute(&mut *tx).await.map_err(internal)?;
        if mode == "quiz" {
            sqlx::query("UPDATE foreign_vocabularies SET times_quizzed=times_quizzed+1,last_quizzed_at=now(),updated_at=now(),last_correct_at=CASE WHEN $3 THEN now() ELSE last_correct_at END WHERE id=$1 AND user_id=$2").bind(id).bind(owner).bind(correct).execute(&mut *tx).await.map_err(internal)?;
        }
        let score = stored["score"]
            .as_i64()
            .unwrap_or(0)
            .min(i64::from(i32::MAX)) as i32;
        let previous_best: i32 = sqlx::query_scalar(
            "SELECT best_correct FROM foreign_game_records WHERE user_id=$1 AND game=$2",
        )
        .bind(owner)
        .bind(mode)
        .fetch_optional(&mut *tx)
        .await
        .map_err(internal)?
        .unwrap_or(0);
        if correct && score > previous_best {
            stored["celebrateRecord"] = json!(score);
        }
        sqlx::query("INSERT INTO foreign_game_records(user_id,game,best_correct) VALUES($1,$2,$3) ON CONFLICT(user_id,game) DO UPDATE SET best_correct=GREATEST(foreign_game_records.best_correct,EXCLUDED.best_correct)").bind(owner).bind(mode).bind(score).execute(&mut *tx).await.map_err(internal)?;
    }
    sqlx::query("INSERT INTO foreign_game_sessions(user_id,mode,state) VALUES($1,$2,$3) ON CONFLICT(user_id,mode) DO UPDATE SET state=EXCLUDED.state,updated_at=now()").bind(owner).bind(mode).bind(&stored).execute(&mut *tx).await.map_err(internal)?;
    let best: i32 = sqlx::query_scalar(
        "SELECT best_correct FROM foreign_game_records WHERE user_id=$1 AND game=$2",
    )
    .bind(owner)
    .bind(mode)
    .fetch_optional(&mut *tx)
    .await
    .map_err(internal)?
    .unwrap_or(0);
    tx.commit().await.map_err(internal)?;
    Ok(Json(
        json!({"props":games::public(&stored,i64::from(best))}),
    ))
}
