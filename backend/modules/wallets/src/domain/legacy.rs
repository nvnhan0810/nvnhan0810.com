//! Maps an unfiltered snapshot of the Laravel finance tables into one account.
use super::*;
use std::collections::HashSet;

fn rows(snapshot: &Value, table: &str) -> Result<Vec<Value>> {
    let mut rows = snapshot[table]
        .as_array()
        .cloned()
        .ok_or_else(|| format!("Missing table: {table}"))?;
    for row in &mut rows {
        let object = row.as_object_mut().ok_or("Invalid source row")?;
        object.remove("user_id");
        if let Some(reminded) = object
            .get("reminded_telegram_at")
            .and_then(Value::as_str)
            .filter(|s| s.len() >= 10)
        {
            let day = reminded[..10].to_owned();
            object.insert("reminded_on".into(), json!(day));
        }
        // JSON consumers compare ids as numbers and flags as booleans.
        for (key, value) in object {
            if key == "id"
                || key.ends_with("_id")
                || [
                    "month_index",
                    "schedule_month_index",
                    "months_paid",
                    "term_months",
                    "order",
                    "day_of_month",
                    "payment_day",
                    "statement_day",
                    "days",
                ]
                .contains(&key.as_str())
            {
                if let Some(raw) = value.as_str() {
                    *value = json!(
                        raw.parse::<i64>()
                            .map_err(|_| format!("Invalid integer: {key}"))?
                    );
                }
            } else if key.starts_with("is_") || key == "reduces_principal" {
                *value = json!(value == true || value == 1 || value == "1");
            }
        }
    }
    Ok(rows)
}
fn reference(row: &Value, key: &str, ids: &HashSet<i64>) -> Result<()> {
    let id = number(row, key);
    if id > 0 && !ids.contains(&id) {
        return Err(format!("Dangling {key}={id}"));
    }
    Ok(())
}
pub fn convert(snapshot: &Value) -> Result<Account> {
    let mut a = Account {
        wallets: rows(snapshot, "wallets")?,
        templates: rows(snapshot, "transaction_templates")?,
        loans: rows(snapshot, "loans")?,
        recurring: rows(snapshot, "recurring_items")?,
        reminders: rows(snapshot, "recurring_occurrences")?,
        holidays: rows(snapshot, "holidays")?,
        ..Account::default()
    };
    let mut periods = rows(snapshot, "loan_custom_schedules")?;
    let mut calculated = rows(snapshot, "calculated_schedules")?;
    let payments = rows(snapshot, "payments")?;
    let settings = rows(snapshot, "settings")?;
    let wallet_ids: HashSet<_> = a.wallets.iter().map(|v| number(v, "id")).collect();
    let loan_ids: HashSet<_> = a.loans.iter().map(|v| number(v, "id")).collect();
    let recurring_ids: HashSet<_> = a.recurring.iter().map(|v| number(v, "id")).collect();
    for row in a
        .wallets
        .iter()
        .chain(&a.templates)
        .chain(&a.loans)
        .chain(&a.recurring)
        .chain(&a.reminders)
        .chain(&a.holidays)
        .chain(&periods)
        .chain(&payments)
    {
        a.next_id = a.next_id.max(number(row, "id"));
    }
    for row in &mut calculated {
        row["id"] = json!(a.id());
    }
    periods.extend(calculated);
    for row in periods.iter().chain(&payments) {
        reference(row, "loan_id", &loan_ids)?;
    }
    for row in &a.templates {
        for key in ["default_wallet_id", "from_wallet_id", "to_wallet_id"] {
            reference(row, key, &wallet_ids)?;
        }
    }
    for row in &a.recurring {
        reference(row, "wallet_id", &wallet_ids)?;
    }
    for row in &mut a.reminders {
        reference(row, "recurring_item_id", &recurring_ids)?;
        let item = a
            .recurring
            .iter()
            .find(|item| item["id"] == row["recurring_item_id"])
            .ok_or("Missing recurring item")?;
        for key in ["name", "type", "wallet_id"] {
            row[key] = item[key].clone();
        }
        row["amount"] = row["expected_amount"].clone();
        row.as_object_mut().unwrap().remove("transaction_id");
    }
    for loan in &mut a.loans {
        reference(loan, "wallet_id", &wallet_ids)?;
        let mut schedule: Vec<_> = periods
            .iter()
            .filter(|row| row["loan_id"] == loan["id"])
            .cloned()
            .collect();
        schedule.sort_by_key(|r| number(r, "month_index"));
        let mut history: Vec<_> = payments
            .iter()
            .filter(|row| row["loan_id"] == loan["id"])
            .cloned()
            .collect();
        for row in &mut schedule {
            let due = if text(row, "due_date").is_empty() {
                text(row, "paid_at")
            } else {
                text(row, "due_date")
            };
            let due = date(due)?;
            row["due_date"] = json!(due.to_string());
            row["due_date_label"] = json!(due.format("%d/%m/%Y").to_string());
            let paid = text(row, "status") == "paid"
                || number(row, "month_index") <= number(loan, "months_paid")
                || history.iter().any(|p| {
                    number(p, "id") == number(row, "payment_id")
                        || number(p, "schedule_month_index") == number(row, "month_index")
                });
            row["is_paid"] = json!(paid);
            row["status"] = json!(if paid {
                "paid"
            } else if due < today() {
                "overdue"
            } else {
                "pending"
            });
        }
        for p in &mut history {
            let paid = date(text(p, "paid_at"))?;
            p["paid_at"] = json!(paid.format("%d/%m/%Y").to_string());
            p["month_index"] = p["schedule_month_index"].clone();
            p["is_early"] = json!(text(p, "kind") == "early");
            p["kind_label"] = json!(match text(p, "kind") {
                "early" => "Trả trước",
                "settlement" => "Tất toán",
                _ => "Đúng kỳ",
            });
            if let Some(row) = schedule.iter().find(|r| {
                number(r, "payment_id") == number(p, "id")
                    || number(r, "month_index") == number(p, "schedule_month_index")
            }) {
                p["period_id"] = row["id"].clone();
            }
            p.as_object_mut().unwrap().remove("transaction_id");
        }
        loan["custom_schedule"] = json!(schedule);
        loan["schedule"] = json!(schedule);
        loan["payments"] = json!(history);
        loan["remaining_principal"] = money(lending::remaining(loan));
    }
    a.settings = json!({"recurring_alert_days":3,"telegram_enabled":false,"telegram_chat_id":""});
    let mut keys = HashSet::new();
    for setting in settings {
        let key = text(&setting, "key");
        if !keys.insert(key.to_owned()) {
            return Err(format!("Duplicate setting key: {key}"));
        }
        a.settings[key] = setting["value"].clone();
    }
    let raw = a.settings["telegram_enabled"].clone();
    a.settings["telegram_enabled"] = json!(raw == true || raw == 1 || raw == "1");
    a.settings["recurring_alert_days"] = json!(number(&a.settings, "recurring_alert_days"));
    if keys.iter().any(|key| key.starts_with("debt_goal_")) {
        a.goal = json!({"name":a.settings["debt_goal_name"],"target_amount":a.settings["debt_goal_target_amount"],"wallet_id":number(&a.settings,"debt_goal_wallet_id"),"loan_id":number(&a.settings,"debt_goal_loan_id"),"settlement_fees":[]});
        if let Some(raw) = a.settings["debt_goal_settlement_fees"]
            .as_str()
            .filter(|v| !v.is_empty())
        {
            a.goal["settlement_fees"] =
                serde_json::from_str(raw).map_err(|_| "Invalid settlement fee JSON")?;
        }
        reference(&a.goal, "wallet_id", &wallet_ids)?;
        reference(&a.goal, "loan_id", &loan_ids)?;
    }
    Ok(a)
}
