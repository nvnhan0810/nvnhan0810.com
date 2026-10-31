use crate::domain::dictionary::Entry;
use serde::Serialize;
use sqlx::{PgPool, Postgres, Transaction};
use uuid::Uuid;
#[derive(Serialize, sqlx::FromRow)]
pub struct Vocabulary {
    pub id: i64,
    pub word: String,
    pub entry: serde_json::Value,
    pub created_at: String,
}
pub async fn save_tx(
    tx: &mut Transaction<'_, Postgres>,
    user: Uuid,
    entry: &Entry,
) -> Result<i64, sqlx::Error> {
    // First saved dictionary data wins; saving again never overwrites a curated/shared entry.
    let dictionary_id: i64 = sqlx::query_scalar("INSERT INTO foreign_dictionary_entries(word,payload) VALUES($1,$2) ON CONFLICT(word) DO UPDATE SET word=EXCLUDED.word RETURNING id")
        .bind(&entry.word).bind(serde_json::to_value(entry).expect("entry serialization")).fetch_one(&mut **tx).await?;
    sqlx::query_scalar("INSERT INTO foreign_vocabularies(user_id,dictionary_entry_id) VALUES($1,$2) ON CONFLICT(user_id,dictionary_entry_id) DO UPDATE SET user_id=EXCLUDED.user_id RETURNING id")
        .bind(user).bind(dictionary_id).fetch_one(&mut **tx).await
}
pub async fn save(pool: &PgPool, user: Uuid, entry: &Entry) -> Result<i64, sqlx::Error> {
    let mut tx = pool.begin().await?;
    let id = save_tx(&mut tx, user, entry).await?;
    tx.commit().await?;
    Ok(id)
}
pub async fn list(
    pool: &PgPool,
    user: Uuid,
    q: &str,
    before: i64,
) -> Result<Vec<Vocabulary>, sqlx::Error> {
    sqlx::query_as("SELECT v.id,e.word,e.payload AS entry,v.created_at::text AS created_at FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE v.user_id=$1 AND v.id < $2 AND ($3='' OR strpos(e.word,lower($3))>0) ORDER BY v.id DESC LIMIT 31")
        .bind(user).bind(before).bind(q).fetch_all(pool).await
}
