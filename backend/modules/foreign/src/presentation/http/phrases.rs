//! Phrases have an independent lifetime; replacing links never deletes a phrase.
use super::{
    media::bad,
    vocabulary::{ApiError, internal},
};
use crate::{
    application::vocabulary::validate,
    domain::{dictionary::Entry, games},
};
use serde_json::{Value, json};
use sqlx::{PgPool, Postgres, Transaction};
use uuid::Uuid;

pub fn decode(data: &Value) -> Result<Entry, ApiError> {
    let meanings: Vec<_> = data["meanings"]
        .as_array()
        .into_iter()
        .flatten()
        .map(|m| {
            json!({
                "partOfSpeech":m["part_of_speech"].as_str().unwrap_or(""),
                "synonyms":m["synonyms"].as_array().cloned().unwrap_or_default(),
                "antonyms":m["antonyms"].as_array().cloned().unwrap_or_default(),
                "definitions":[{"definition":m["definition"].as_str().unwrap_or(""),
                    "example":m["example"].as_str().unwrap_or(""),
                    "examples":m["examples"].as_array().cloned().unwrap_or_default()}]
            })
        })
        .collect();
    let mut entry: Entry = serde_json::from_value(json!({
        "word":data["word"], "phonetic":data["phonetic"].as_str().unwrap_or(""),
        "meanings":meanings,
        "examples":data["examples"].as_array().cloned().unwrap_or_default(),
        "synonyms":data["synonyms"].as_array().cloned().unwrap_or_default(),
        "antonyms":data["antonyms"].as_array().cloned().unwrap_or_default()
    }))
    .map_err(|_| bad("Invalid phrase"))?;
    if let Some(audio) = data.get("audio_url") {
        let audio = audio.as_str().ok_or_else(|| bad("Invalid audio URL"))?;
        if !audio.is_empty() {
            let url = reqwest::Url::parse(audio).map_err(|_| bad("Invalid audio URL"))?;
            if url.scheme() != "https" || !url.username().is_empty() || url.password().is_some() {
                return Err(bad("Invalid audio URL"));
            }
        }
        entry.phonetics.push(crate::domain::dictionary::Phonetic {
            text: entry.phonetic.clone(),
            audio: audio.into(),
        });
    }
    validate(&mut entry).map_err(bad)?;
    Ok(entry)
}
pub fn decode_list(data: &Value) -> Result<Option<Vec<Entry>>, ApiError> {
    let Some(value) = data.get("phrases") else {
        return Ok(None);
    };
    let rows = value
        .as_array()
        .ok_or_else(|| bad("phrases must be an array"))?;
    if rows.len() > 100 {
        return Err(bad("Too many phrases"));
    }
    let mut entries = Vec::new();
    for row in rows {
        let entry = decode(row)?;
        if entries.iter().any(|e: &Entry| e.word == entry.word) {
            return Err(bad("Duplicate phrase"));
        }
        entries.push(entry);
    }
    Ok(Some(entries))
}
pub async fn upsert(
    tx: &mut Transaction<'_, Postgres>,
    owner: Uuid,
    entry: &Entry,
) -> Result<i64, sqlx::Error> {
    sqlx::query_scalar("INSERT INTO foreign_phrases(user_id,phrase,payload) VALUES($1,$2,$3) ON CONFLICT(user_id,phrase) DO UPDATE SET payload=EXCLUDED.payload,updated_at=now() RETURNING id")
        .bind(owner).bind(&entry.word).bind(serde_json::to_value(entry).expect("entry serialization"))
        .fetch_one(&mut **tx).await
}
pub async fn linked(pool: &PgPool, owner: Uuid, word: &str) -> Result<Vec<Value>, ApiError> {
    let rows: Vec<Value> = sqlx::query_scalar("SELECT p.payload FROM foreign_phrases p JOIN foreign_word_phrases l ON l.phrase_id=p.id JOIN foreign_vocabularies v ON v.id=l.vocabulary_id JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE p.user_id=$1 AND v.user_id=$1 AND e.word=$2 ORDER BY p.phrase")
        .bind(owner).bind(word).fetch_all(pool).await.map_err(internal)?;
    Ok(rows.iter().map(games::reveal).collect())
}
pub async fn list(pool: &PgPool, owner: Uuid) -> Result<Value, ApiError> {
    let rows: Vec<Value> =
        sqlx::query_scalar("SELECT payload FROM foreign_phrases WHERE user_id=$1 ORDER BY phrase")
            .bind(owner)
            .fetch_all(pool)
            .await
            .map_err(internal)?;
    Ok(json!({"items": rows.iter().map(games::reveal).collect::<Vec<_>>()}))
}
pub async fn exact(pool: &PgPool, owner: Uuid, word: &str) -> Result<Option<Value>, ApiError> {
    sqlx::query_scalar("SELECT payload FROM foreign_phrases WHERE user_id=$1 AND phrase=$2")
        .bind(owner)
        .bind(word)
        .fetch_optional(pool)
        .await
        .map_err(internal)
}
pub async fn suggestions(pool: &PgPool, owner: Uuid, word: &str) -> Result<Vec<Value>, ApiError> {
    sqlx::query_scalar("SELECT jsonb_build_object('word',term,'kind',kind) FROM (SELECT word AS term,'word' AS kind FROM foreign_dictionary_entries WHERE strpos(word,$2)>0 UNION ALL SELECT phrase,'phrase' FROM foreign_phrases WHERE user_id=$1 AND strpos(phrase,$2)>0) matches ORDER BY (term=$2) DESC,term,kind LIMIT 30")
        .bind(owner).bind(word).fetch_all(pool).await.map_err(internal)
}
