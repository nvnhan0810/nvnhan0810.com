use super::schema;
use crate::domain::{Account, date, number, text};
use serde_json::{Value, json};
use sqlx::{Postgres, Transaction};
use uuid::Uuid;

type Tx<'a> = Transaction<'a, Postgres>;
fn invalid(message: impl ToString) -> sqlx::Error {
    sqlx::Error::Protocol(message.to_string())
}
async fn read(tx: &mut Tx<'_>, owner: Uuid, table: &str) -> Result<Vec<Value>, sqlx::Error> {
    sqlx::query_scalar(sqlx::AssertSqlSafe(format!(
        "SELECT to_jsonb(t)-'user_id' FROM {table} t WHERE user_id=$1 ORDER BY id"
    )))
    .bind(owner)
    .fetch_all(&mut **tx)
    .await
}
fn row_for_storage(row: &Value, columns: &[(&str, &str)]) -> Result<Value, sqlx::Error> {
    let mut row = row.clone();
    for &(key, kind) in columns {
        let value = &row[key];
        if value.is_null() {
            continue;
        }
        if value == "" || ((key.ends_with("_id") || key == "id") && (value == 0 || value == "0")) {
            row[key] = Value::Null;
        } else if kind == "date" {
            row[key] = json!(
                date(
                    value
                        .as_str()
                        .ok_or_else(|| invalid(format!("Invalid {key}")))?
                )
                .map_err(invalid)?
                .to_string()
            );
        } else if kind == "text" && !value.is_string() {
            row[key] = json!(value.to_string());
        }
    }
    Ok(row)
}
/// Reconcile by primary key; unchanged records aren't rewritten. Identifiers come only from schema.rs.
async fn sync(
    tx: &mut Tx<'_>,
    owner: Uuid,
    table: &str,
    columns: &[(&str, &str)],
    rows: &[Value],
) -> Result<(), sqlx::Error> {
    let ids: Vec<_> = rows.iter().map(|r| number(r, "id")).collect();
    sqlx::query(sqlx::AssertSqlSafe(format!(
        "DELETE FROM {table} WHERE user_id=$1 AND NOT (id=ANY($2))"
    )))
    .bind(owner)
    .bind(&ids)
    .execute(&mut **tx)
    .await?;
    let names: Vec<_> = std::iter::once("id")
        .chain(columns.iter().map(|c| c.0))
        .collect();
    let quoted: Vec<_> = names.iter().map(|c| format!("\"{c}\"")).collect();
    let selects = quoted
        .iter()
        .map(|c| format!("r.{c}"))
        .collect::<Vec<_>>()
        .join(",");
    let updates = quoted
        .iter()
        .skip(1)
        .map(|c| format!("{c}=EXCLUDED.{c}"))
        .collect::<Vec<_>>()
        .join(",");
    let left = quoted
        .iter()
        .skip(1)
        .map(|c| format!("{table}.{c}"))
        .collect::<Vec<_>>()
        .join(",");
    let right = quoted
        .iter()
        .skip(1)
        .map(|c| format!("EXCLUDED.{c}"))
        .collect::<Vec<_>>()
        .join(",");
    let sql = format!(
        "INSERT INTO {table}(user_id,{}) SELECT $1,{selects} FROM jsonb_populate_record(NULL::{table},$2) r ON CONFLICT(user_id,id) DO UPDATE SET {updates} WHERE ROW({left}) IS DISTINCT FROM ROW({right})",
        quoted.join(",")
    );
    for row in rows {
        let row = row_for_storage(row, columns)?;
        sqlx::query(sqlx::AssertSqlSafe(sql.as_str()))
            .bind(owner)
            .bind(row)
            .execute(&mut **tx)
            .await?;
    }
    Ok(())
}
pub async fn load(tx: &mut Tx<'_>, owner: Uuid) -> Result<Account, sqlx::Error> {
    sqlx::query("INSERT INTO finance_profiles(user_id) VALUES($1) ON CONFLICT DO NOTHING")
        .bind(owner)
        .execute(&mut **tx)
        .await?;
    let next_id =
        sqlx::query_scalar("SELECT next_id FROM finance_profiles WHERE user_id=$1 FOR UPDATE")
            .bind(owner)
            .fetch_one(&mut **tx)
            .await?;
    let mut a = Account {
        next_id,
        ..Account::default()
    };
    a.wallets = read(tx, owner, "wallets").await?;
    a.templates = read(tx, owner, "transaction_templates").await?;
    a.transactions = read(tx, owner, "transactions").await?;
    a.loans = read(tx, owner, "loans").await?;
    a.recurring = read(tx, owner, "recurring_items").await?;
    a.reminders = read(tx, owner, "recurring_occurrences").await?;
    a.holidays = sqlx::query_scalar("SELECT to_jsonb(h) FROM holidays h ORDER BY date,id")
        .fetch_all(&mut **tx)
        .await?;
    a.original_holidays = a.holidays.clone();
    let periods = read(tx, owner, "loan_periods").await?;
    let payments = read(tx, owner, "loan_payments").await?;
    for loan in &mut a.loans {
        let mut schedule: Vec<_> = periods
            .iter()
            .filter(|r| r["loan_id"] == loan["id"])
            .cloned()
            .collect();
        schedule.sort_by_key(|r| number(r, "month_index"));
        for row in &mut schedule {
            row["due_date_label"] = json!(
                date(text(row, "due_date"))
                    .map_err(invalid)?
                    .format("%d/%m/%Y")
                    .to_string()
            );
        }
        let mut history: Vec<_> = payments
            .iter()
            .filter(|r| r["loan_id"] == loan["id"])
            .cloned()
            .collect();
        for row in &mut history {
            row["paid_at"] = json!(
                date(text(row, "paid_at"))
                    .map_err(invalid)?
                    .format("%d/%m/%Y")
                    .to_string()
            );
            row["kind_label"] = json!(match text(row, "kind") {
                "early" => "Trả trước",
                "settlement" => "Tất toán",
                _ => "Đúng kỳ",
            });
        }
        loan["schedule"] = json!(schedule);
        if text(loan, "interest_calculation_method") == "custom" {
            loan["custom_schedule"] = json!(schedule);
        }
        loan["payments"] = json!(history);
    }
    for t in &mut a.transactions {
        let wallet = a.wallets.iter().find(|w| w["id"] == t["wallet_id"]);
        t["wallet_name"] = wallet.map(|w| w["name"].clone()).unwrap_or(Value::Null);
        t["wallet"] = json!({"id":t["wallet_id"],"name":t["wallet_name"]});
        t["type_label"] = json!(match text(t, "type") {
            "income" => "Thu",
            "expense" => "Chi",
            _ => "Cân đối",
        });
    }
    for t in &mut a.templates {
        t["type_label"] = json!(match text(t, "type") {
            "income" => "Thu",
            "expense" => "Chi",
            "transfer" => "Chuyển ví",
            _ => "Cân đối",
        });
    }
    let settings: Vec<(String, Option<String>)> =
        sqlx::query_as("SELECT key,value FROM user_settings WHERE user_id=$1")
            .bind(owner)
            .fetch_all(&mut **tx)
            .await?;
    a.normalize();
    for (key, value) in settings {
        a.settings[&key] = match key.as_str() {
            "telegram_enabled" => json!(value.as_deref().is_some_and(|v| v == "true" || v == "1")),
            "recurring_alert_days" => json!(value.and_then(|v| v.parse::<i64>().ok()).unwrap_or(7)),
            _ => value.map(Value::String).unwrap_or(Value::Null),
        };
    }
    a.goal = read(tx, owner, "debt_goals")
        .await?
        .into_iter()
        .next()
        .unwrap_or(Value::Null);
    if a.goal.is_object() {
        a.goal["settlement_fees"] = json!(read(tx, owner, "settlement_fees").await?);
    }
    Ok(a)
}
pub async fn save(tx: &mut Tx<'_>, owner: Uuid, a: &Account) -> Result<(), sqlx::Error> {
    // The caller holds the profile row lock from load(). Foreign keys are deferred
    // so each entity collection can be reconciled without breaking payment cycles.
    sqlx::query("UPDATE finance_profiles SET next_id=$2 WHERE user_id=$1")
        .bind(owner)
        .bind(a.next_id)
        .execute(&mut **tx)
        .await?;
    sync(tx, owner, "wallets", schema::WALLETS, &a.wallets).await?;
    sync(
        tx,
        owner,
        "transaction_templates",
        schema::TRANSACTION_TEMPLATES,
        &a.templates,
    )
    .await?;
    sync(tx, owner, "loans", schema::LOANS, &a.loans).await?;
    let mut periods = Vec::new();
    let mut payments = Vec::new();
    for loan in &a.loans {
        for (key, out) in [("schedule", &mut periods), ("payments", &mut payments)] {
            for row in loan[key].as_array().into_iter().flatten() {
                let mut row = row.clone();
                row["loan_id"] = loan["id"].clone();
                out.push(row);
            }
        }
    }
    sync(tx, owner, "loan_periods", schema::LOAN_PERIODS, &periods).await?;
    sync(tx, owner, "loan_payments", schema::LOAN_PAYMENTS, &payments).await?;
    sync(
        tx,
        owner,
        "recurring_items",
        schema::RECURRING_ITEMS,
        &a.recurring,
    )
    .await?;
    sync(
        tx,
        owner,
        "recurring_occurrences",
        schema::RECURRING_OCCURRENCES,
        &a.reminders,
    )
    .await?;
    let mut transfers = std::collections::BTreeMap::new();
    for t in &a.transactions {
        let id = number(t, "wallet_transfer_id");
        if id == 0 {
            continue;
        }
        let row=transfers.entry(id).or_insert_with(||json!({"id":id,"amount":0,"fee":0,"description":t["description"],"note":t["note"],"transacted_at":t["transacted_at"],"transaction_template_id":t["transaction_template_id"]}));
        if number(t, "delta") < 0 {
            row["from_wallet_id"] = t["wallet_id"].clone();
            row["outgoing_amount"] = t["amount"].clone();
        } else {
            row["to_wallet_id"] = t["wallet_id"].clone();
            row["amount"] = t["amount"].clone();
        }
    }
    for row in transfers.values_mut() {
        row["fee"] = crate::domain::money(
            crate::domain::cents(row, "outgoing_amount").map_err(invalid)?
                - crate::domain::cents(row, "amount").map_err(invalid)?,
        );
    }
    sync(
        tx,
        owner,
        "wallet_transfers",
        schema::WALLET_TRANSFERS,
        &transfers.into_values().collect::<Vec<_>>(),
    )
    .await?;
    sync(
        tx,
        owner,
        "transactions",
        schema::TRANSACTIONS,
        &a.transactions,
    )
    .await?;
    let mut goal = a.goal.clone();
    let mut fees = Vec::new();
    if goal.is_object() {
        goal["id"] = json!(1);
        for (i, row) in goal["settlement_fees"]
            .as_array()
            .into_iter()
            .flatten()
            .enumerate()
        {
            let mut row = row.clone();
            row["id"] = json!(i + 1);
            row["goal_id"] = json!(1);
            fees.push(row);
        }
    }
    sync(
        tx,
        owner,
        "debt_goals",
        schema::DEBT_GOALS,
        &if goal.is_object() { vec![goal] } else { vec![] },
    )
    .await?;
    sync(tx, owner, "settlement_fees", schema::SETTLEMENT_FEES, &fees).await?;
    let keys: Vec<_> = a
        .settings
        .as_object()
        .into_iter()
        .flatten()
        .map(|(k, _)| k.as_str())
        .collect();
    sqlx::query("DELETE FROM user_settings WHERE user_id=$1 AND NOT(key=ANY($2))")
        .bind(owner)
        .bind(&keys)
        .execute(&mut **tx)
        .await?;
    for (key, value) in a.settings.as_object().into_iter().flatten() {
        let value = if value.is_null() {
            None
        } else {
            Some(
                value
                    .as_str()
                    .map(str::to_owned)
                    .unwrap_or_else(|| value.to_string()),
            )
        };
        sqlx::query("INSERT INTO user_settings(user_id,key,value) VALUES($1,$2,$3) ON CONFLICT(user_id,key) DO UPDATE SET value=EXCLUDED.value WHERE user_settings.value IS DISTINCT FROM EXCLUDED.value").bind(owner).bind(key).bind(value).execute(&mut **tx).await?;
    }
    // Apply only this request's calendar changes; another user's calendar additions survive.
    for old in &a.original_holidays {
        if !a.holidays.iter().any(|h| h["date"] == old["date"]) {
            sqlx::query("DELETE FROM holidays WHERE date=$1::text::date")
                .bind(text(old, "date"))
                .execute(&mut **tx)
                .await?;
        }
    }
    for row in &a.holidays {
        if a.original_holidays.iter().any(|old| old == row) {
            continue;
        }
        let row = row_for_storage(row, schema::HOLIDAYS)?;
        sqlx::query("INSERT INTO holidays(date,name,type,created_at,updated_at) SELECT r.date,r.name,r.type,r.created_at,r.updated_at FROM jsonb_populate_record(NULL::holidays,$1) r ON CONFLICT(date) DO UPDATE SET name=EXCLUDED.name,type=EXCLUDED.type WHERE ROW(holidays.name,holidays.type) IS DISTINCT FROM ROW(EXCLUDED.name,EXCLUDED.type)").bind(row).execute(&mut **tx).await?;
    }
    Ok(())
}
