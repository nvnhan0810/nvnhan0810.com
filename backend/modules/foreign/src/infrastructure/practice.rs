//! Shared practice selection for Quiz and every vocabulary game.
use crate::domain::games;
use serde_json::{Value, json};
use sqlx::PgConnection;
use uuid::Uuid;

pub async fn candidates(
    connection: &mut PgConnection,
    owner: Uuid,
    mode: &str,
) -> Result<Vec<(i64, Value)>, sqlx::Error> {
    // Exponential races sample without replacement. Familiar words gradually
    // recover their weight as the rating ages; every level can still appear.
    let rows: Vec<(i64, Value, Option<i16>, Value)> = sqlx::query_as(
        r#"SELECT v.id, COALESCE(v.entry_override,e.payload), m.level,
                  COALESCE(m.meaning_counts,'{}'::jsonb)
           FROM foreign_vocabularies v
           JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id
           LEFT JOIN foreign_vocabulary_memory m ON m.vocabulary_id=v.id
           WHERE v.user_id=$1
             AND jsonb_array_length(COALESCE(v.entry_override,e.payload)->'meanings')>0
             AND ($2='quiz' OR (e.word ~ '^[a-z]+$' AND length(e.word) BETWEEN
                 (CASE WHEN $2='wordle' THEN 5 ELSE 3 END) AND
                 (CASE WHEN $2='wordle' THEN 5 WHEN $2='word-search' THEN 8 ELSE 12 END)))
           ORDER BY -ln(GREATEST(random(),0.000001)) /
             (CASE m.level
                WHEN 1 THEN 6.0
                WHEN 2 THEN 3.0
                WHEN 3 THEN 0.4 + 0.6 * LEAST(1.0, GREATEST(0.0,
                    EXTRACT(EPOCH FROM (now()-m.rated_at))/604800.0))
                WHEN 4 THEN 0.08 + 0.92 * LEAST(1.0, GREATEST(0.0,
                    EXTRACT(EPOCH FROM (now()-m.rated_at))/2592000.0))
                ELSE 4.0 END)
           LIMIT 32"#,
    )
    .bind(owner)
    .bind(mode)
    .fetch_all(connection)
    .await?;
    Ok(rows
        .into_iter()
        .filter_map(|(id, entry, level, counts)| {
            let mut entry = games::select_meaning(&entry, &counts)?;
            entry["_memory_level"] = json!(level);
            Some((id, entry))
        })
        .collect())
}

pub async fn record_meaning(
    connection: &mut PgConnection,
    id: i64,
    entry: &Value,
) -> Result<(), sqlx::Error> {
    let Some(key) = entry["_meaning_key"].as_str() else {
        return Ok(());
    };
    sqlx::query(
        r#"INSERT INTO foreign_vocabulary_memory(vocabulary_id,meaning_counts)
           VALUES($1,jsonb_build_object($2::text,1))
           ON CONFLICT(vocabulary_id) DO UPDATE SET meaning_counts=
             jsonb_set(foreign_vocabulary_memory.meaning_counts,ARRAY[$2::text],
               to_jsonb(COALESCE((foreign_vocabulary_memory.meaning_counts->>$2)::bigint,0)+1))"#,
    )
    .bind(id)
    .bind(key)
    .execute(connection)
    .await?;
    Ok(())
}

pub async fn rate(
    connection: &mut PgConnection,
    owner: Uuid,
    id: i64,
    level: i16,
) -> Result<bool, sqlx::Error> {
    let result = sqlx::query(
        r#"INSERT INTO foreign_vocabulary_memory(vocabulary_id,level,rated_at)
           SELECT id,$3,now() FROM foreign_vocabularies WHERE id=$1 AND user_id=$2
           ON CONFLICT(vocabulary_id) DO UPDATE SET level=EXCLUDED.level,rated_at=now()"#,
    )
    .bind(id)
    .bind(owner)
    .bind(level)
    .execute(connection)
    .await?;
    Ok(result.rows_affected() == 1)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[tokio::test]
    #[ignore = "requires FOREIGN_TEST_TARGET (PostgreSQL with schema creation permission)"]
    async fn memory_selection_rotation_and_migration_work_in_postgres() {
        let pool = sqlx::PgPool::connect(&std::env::var("FOREIGN_TEST_TARGET").unwrap())
            .await
            .unwrap();
        let mut tx = pool.begin().await.unwrap();
        let schema = format!("memory_test_{}", Uuid::new_v4().simple());
        // The identifier contains only a fixed prefix and UUID hex digits.
        sqlx::raw_sql(sqlx::AssertSqlSafe(format!("CREATE SCHEMA {schema}; SET LOCAL search_path TO {schema};
            CREATE TABLE foreign_dictionary_entries (id BIGINT PRIMARY KEY, word TEXT, payload JSONB);
            CREATE TABLE foreign_vocabularies (id BIGINT PRIMARY KEY, user_id UUID,
                dictionary_entry_id BIGINT, entry_override JSONB);")))
            .execute(&mut *tx).await.unwrap();
        sqlx::raw_sql(include_str!(
            "../../migrations/20261105000001_vocabulary_memory.up.sql"
        ))
        .execute(&mut *tx)
        .await
        .unwrap();
        let owner = Uuid::new_v4();
        let other = Uuid::new_v4();
        for (index, word) in ["apple", "peach", "lemon", "mango", "other"]
            .iter()
            .enumerate()
        {
            let id = index as i64 + 1;
            let entry = json!({"word":word,"meanings":[{"partOfSpeech":"noun","definitions":[
                {"definition":format!("First meaning of {word}"),"example":"First example"},
                {"definition":format!("Second meaning of {word}"),"example":"Second example"}
            ]}]});
            sqlx::query("INSERT INTO foreign_dictionary_entries VALUES($1,$2,$3)")
                .bind(id)
                .bind(word)
                .bind(entry)
                .execute(&mut *tx)
                .await
                .unwrap();
            sqlx::query("INSERT INTO foreign_vocabularies VALUES($1,$2,$1,NULL)")
                .bind(id)
                .bind(if id == 5 { other } else { owner })
                .execute(&mut *tx)
                .await
                .unwrap();
        }
        let rows = candidates(&mut tx, owner, "quiz").await.unwrap();
        assert_eq!(rows.len(), 4);
        let (_, selected) = rows.iter().find(|(id, _)| *id == 1).unwrap();
        record_meaning(&mut tx, 1, selected).await.unwrap();
        assert!(rate(&mut tx, owner, 1, 4).await.unwrap());
        assert!(!rate(&mut tx, other, 1, 1).await.unwrap());
        let next = candidates(&mut tx, owner, "quiz").await.unwrap();
        let (_, rotated) = next.iter().find(|(id, _)| *id == 1).unwrap();
        assert_ne!(selected["_meaning_key"], rotated["_meaning_key"]);
        assert_eq!(rotated["_memory_level"], 4);
        record_meaning(&mut tx, 1, selected).await.unwrap();
        let counts: Value = sqlx::query_scalar(
            "SELECT meaning_counts FROM foreign_vocabulary_memory WHERE vocabulary_id=1",
        )
        .fetch_one(&mut *tx)
        .await
        .unwrap();
        assert_eq!(counts[selected["_meaning_key"].as_str().unwrap()], 2);
        for id in 2..=4 {
            assert!(rate(&mut tx, owner, id, 1).await.unwrap());
        }
        // Seed PostgreSQL's PRNG: verify weighting is used by every activity,
        // while remembered words remain eligible in the candidate pool.
        for mode in ["quiz", "scramble", "wordle", "hangman", "word-search"] {
            sqlx::query("SELECT setseed(0.42)")
                .execute(&mut *tx)
                .await
                .unwrap();
            let mut remembered_first = 0;
            for _ in 0..128 {
                let rows = candidates(&mut tx, owner, mode).await.unwrap();
                assert_eq!(rows.len(), 4);
                assert!(rows.iter().any(|(id, _)| *id == 1));
                assert!(rows.iter().all(|(id, _)| *id != 5));
                remembered_first += usize::from(rows[0].0 == 1);
            }
            assert!(remembered_first < 16, "{mode}: {remembered_first}");
        }
        // Cascading deletion removes memory data with the saved word.
        sqlx::query("DELETE FROM foreign_vocabularies WHERE id=1")
            .execute(&mut *tx)
            .await
            .unwrap();
        let remaining: i64 = sqlx::query_scalar(
            "SELECT count(*) FROM foreign_vocabulary_memory WHERE vocabulary_id=1",
        )
        .fetch_one(&mut *tx)
        .await
        .unwrap();
        assert_eq!(remaining, 0);
        sqlx::raw_sql(include_str!(
            "../../migrations/20261105000001_vocabulary_memory.down.sql"
        ))
        .execute(&mut *tx)
        .await
        .unwrap();
        tx.rollback().await.unwrap();
    }
}
