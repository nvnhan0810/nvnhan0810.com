use crate::{domain::dictionary::Entry, infrastructure::vocabulary};
use app_config::ConfigSource;
use sqlx::{PgPool, Row, postgres::PgPoolOptions};
use std::collections::HashMap;
use uuid::Uuid;
type Result<T> = std::result::Result<T, Box<dyn std::error::Error + Send + Sync>>;

pub async fn create_run(pool: &PgPool, dry_run: bool) -> std::result::Result<Uuid, sqlx::Error> {
    // A worker refreshes its lease after each committed batch. Recover abandoned processes.
    sqlx::query("UPDATE foreign_import_runs SET status='failed',error='worker_lease_expired',finished_at=now() WHERE status IN ('queued','running') AND heartbeat_at < now()-interval '10 minutes'").execute(pool).await?;
    let id = Uuid::new_v4();
    sqlx::query("INSERT INTO foreign_import_runs(id,status,dry_run) VALUES($1,'queued',$2)")
        .bind(id)
        .bind(dry_run)
        .execute(pool)
        .await?;
    Ok(id)
}
pub async fn run(pool: &PgPool, id: Uuid, dry_run: bool, config: &ConfigSource) -> Result<()> {
    let result = execute(pool, id, dry_run, config).await;
    match &result {
        Ok(()) => {
            sqlx::query(
                "UPDATE foreign_import_runs SET status='completed',finished_at=now() WHERE id=$1 AND status='running'",
            )
            .bind(id)
            .execute(pool)
            .await?;
        }
        Err(_) => {
            let detail = if let Some(error) = result
                .as_ref()
                .err()
                .and_then(|error| error.downcast_ref::<sqlx::Error>())
            {
                format!(
                    "database_error:{}",
                    error
                        .as_database_error()
                        .and_then(|e| e.code())
                        .map(|s| s.into_owned())
                        .unwrap_or_else(|| "connection_or_schema".into())
                )
            } else {
                result
                    .as_ref()
                    .err()
                    .map(ToString::to_string)
                    .unwrap_or_default()
            };
            sqlx::query("UPDATE foreign_import_runs SET status='failed',error=$2,finished_at=now() WHERE id=$1 AND status IN ('queued','running')").bind(id).bind(detail).execute(pool).await?;
        }
    }
    result
}
async fn execute(pool: &PgPool, id: Uuid, dry_run: bool, config: &ConfigSource) -> Result<()> {
    let claimed = sqlx::query("UPDATE foreign_import_runs SET status='running',started_at=now(),heartbeat_at=now() WHERE id=$1 AND status='queued' AND dry_run=$2").bind(id).bind(dry_run).execute(pool).await?;
    if claimed.rows_affected() != 1 {
        return Err("Import run is not queued or mode differs".into());
    }
    let values = ["HOST", "PORT", "NAME", "USERNAME", "PASSWORD"]
        .into_iter()
        .map(|key| {
            let name = format!("FOREIGN_IMPORT_SOURCE_DB_{key}");
            let value = config
                .optional(&name)
                .ok_or_else(|| format!("Missing {name}"))?;
            Ok((format!("DB_{key}"), value.to_owned()))
        })
        .collect::<std::result::Result<Vec<_>, String>>()?;
    let source_config = ConfigSource::from_values(values);
    if config.optional("DB_HOST") == source_config.optional("DB_HOST")
        && config.optional("DB_PORT") == source_config.optional("DB_PORT")
        && config.optional("DB_NAME") == source_config.optional("DB_NAME")
    {
        return Err("Source and target must be different databases".into());
    }
    let options = app_config::DatabaseConfig::from_source(&source_config)?.connect_options();
    let source = PgPoolOptions::new()
        .max_connections(1)
        .acquire_timeout(std::time::Duration::from_secs(10))
        .connect_with(options)
        .await?;
    let mapping: HashMap<String, Uuid> =
        serde_json::from_str(&config.required("FOREIGN_IMPORT_USER_MAP")?)
            .map_err(|_| "Invalid FOREIGN_IMPORT_USER_MAP JSON")?;
    let source_system = config.required("FOREIGN_IMPORT_SOURCE_ID")?;
    let timezone = config.required("FOREIGN_IMPORT_SOURCE_TIMEZONE")?;
    let mut source_tx = source.begin().await?;
    sqlx::query("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY")
        .execute(&mut *source_tx)
        .await?;
    sqlx::query("SET LOCAL statement_timeout='30s'")
        .execute(&mut *source_tx)
        .await?;
    let owners: Vec<i64> = sqlx::query_scalar("SELECT DISTINCT user_id FROM vocabularies")
        .fetch_all(&mut *source_tx)
        .await?;
    for owner in owners {
        let target = mapping
            .get(&owner.to_string())
            .ok_or_else(|| format!("Unmapped Laravel user id {owner}"))?;
        let valid: bool = sqlx::query_scalar(
            "SELECT EXISTS(SELECT 1 FROM identity_users WHERE id=$1 AND enabled)",
        )
        .bind(target)
        .fetch_one(pool)
        .await?;
        if !valid {
            return Err(
                format!("Mapped Identity user missing/disabled for Laravel user {owner}").into(),
            );
        }
    }
    // Session-level serialization also prevents overlapping workers after lease recovery.
    let mut lock = pool.acquire().await?;
    let locked: bool = sqlx::query_scalar("SELECT pg_try_advisory_lock(31003100)")
        .fetch_one(&mut *lock)
        .await?;
    if !locked {
        return Err("Another import worker is active".into());
    }
    let result = import_rows(
        pool,
        &mut source_tx,
        id,
        dry_run,
        &mapping,
        &source_system,
        &timezone,
    )
    .await;
    sqlx::query("SELECT pg_advisory_unlock(31003100)")
        .execute(&mut *lock)
        .await?;
    source_tx.rollback().await?;
    source.close().await;
    result
}
async fn import_rows(
    pool: &PgPool,
    source: &mut sqlx::Transaction<'_, sqlx::Postgres>,
    id: Uuid,
    dry_run: bool,
    mapping: &HashMap<String, Uuid>,
    source_system: &str,
    timezone: &str,
) -> Result<()> {
    let mut after = 0i64;
    loop {
        let rows = sqlx::query(include_str!("legacy_vocabulary.sql"))
            .bind(after)
            .fetch_all(&mut **source)
            .await?;
        if rows.is_empty() {
            break;
        }
        for row in rows {
            let old_id: i64 = row.try_get("id")?;
            after = old_id;
            let owner: i64 = row.try_get("user_id")?;
            let target = mapping
                .get(&owner.to_string())
                .ok_or("Missing user mapping")?;
            let mut entry: Entry = serde_json::from_value(row.try_get("entry")?)?;
            crate::application::vocabulary::validate(&mut entry)
                .map_err(|_| format!("Invalid legacy dictionary entry at vocabulary {old_id}"))?;
            let snapshot = serde_json::json!({"owner":owner,"entry":entry,"metadata":row.try_get::<serde_json::Value,_>("metadata")?,"times_quizzed":row.try_get::<i32,_>("times_quizzed")?,"last_quizzed_at":row.try_get::<Option<String>,_>("last_quizzed_at")?,"last_correct_at":row.try_get::<Option<String>,_>("last_correct_at")?,"created_at":row.try_get::<String,_>("created_at")?,"updated_at":row.try_get::<String,_>("updated_at")?});
            let mut tx = pool.begin().await?;
            let existing: Option<i64> = sqlx::query_scalar("SELECT target_id FROM foreign_import_records WHERE source_system=$1 AND source_id=$2").bind(source_system).bind(old_id).fetch_optional(&mut *tx).await?;
            if let Some(target_id) = existing {
                let previous:serde_json::Value=sqlx::query_scalar("SELECT source_snapshot FROM foreign_import_records WHERE source_system=$1 AND source_id=$2").bind(source_system).bind(old_id).fetch_one(&mut *tx).await?;
                if previous != snapshot {
                    return Err(
                        format!("Source changed since import at vocabulary {old_id}").into(),
                    );
                }

                let owner: Option<Uuid> =
                    sqlx::query_scalar("SELECT user_id FROM foreign_vocabularies WHERE id=$1")
                        .bind(target_id)
                        .fetch_optional(&mut *tx)
                        .await?;
                if owner != Some(*target) {
                    return Err(
                        format!("User mapping changed at source vocabulary {old_id}").into(),
                    );
                }
            }
            let duplicate: bool = sqlx::query_scalar("SELECT EXISTS(SELECT 1 FROM foreign_vocabularies v JOIN foreign_dictionary_entries e ON e.id=v.dictionary_entry_id WHERE user_id=$1 AND word=$2)").bind(target).bind(&entry.word).fetch_one(&mut *tx).await?;
            let mut imported = false;
            if existing.is_none() {
                if duplicate {
                    return Err(format!("Target word already exists without import mapping at source vocabulary {old_id}").into());
                }
                let shared: Option<serde_json::Value> = sqlx::query_scalar(
                    "SELECT payload FROM foreign_dictionary_entries WHERE word=$1",
                )
                .bind(&entry.word)
                .fetch_optional(&mut *tx)
                .await?;
                if let Some(shared) = shared
                    && shared != serde_json::to_value(&entry)?
                {
                    return Err(format!("Dictionary conflict at source vocabulary {old_id}").into());
                }
                if !dry_run {
                    let target_id = vocabulary::save_tx(&mut tx, *target, &entry).await?;
                    sqlx::query("UPDATE foreign_vocabularies SET times_quizzed=$2,last_quizzed_at=$3::text::timestamp,last_correct_at=$4::text::timestamp,created_at=$5::text::timestamp AT TIME ZONE $6,updated_at=$7::text::timestamp AT TIME ZONE $6 WHERE id=$1")
                        .bind(target_id).bind(row.try_get::<i32,_>("times_quizzed")?).bind(row.try_get::<Option<String>,_>("last_quizzed_at")?).bind(row.try_get::<Option<String>,_>("last_correct_at")?).bind(row.try_get::<String,_>("created_at")?).bind(timezone).bind(row.try_get::<String,_>("updated_at")?).execute(&mut *tx).await?;
                    sqlx::query("UPDATE foreign_dictionary_entries SET metadata=$2 WHERE id=(SELECT dictionary_entry_id FROM foreign_vocabularies WHERE id=$1) AND metadata='{}'::jsonb").bind(target_id).bind(row.try_get::<serde_json::Value,_>("metadata")?).execute(&mut *tx).await?;
                    sqlx::query("INSERT INTO foreign_import_records(source_system,source_id,target_id,source_snapshot) VALUES($1,$2,$3,$4)").bind(source_system).bind(old_id).bind(target_id).bind(snapshot).execute(&mut *tx).await?;
                }
                imported = true;
            }
            sqlx::query("UPDATE foreign_import_runs SET processed=processed+1,imported=imported+$2,skipped=skipped+$3,heartbeat_at=now() WHERE id=$1")
                .bind(id).bind(i64::from(imported)).bind(i64::from(!imported)).execute(&mut *tx).await?;
            tx.commit().await?;
        }
    }
    Ok(())
}
