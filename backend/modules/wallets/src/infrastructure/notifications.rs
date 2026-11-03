use crate::domain::{flag, number, reports, text, today};
use serde_json::json;
use std::time::Duration;

/// Invoked only by the scheduled console command; a page request never sends messages.
pub async fn remind(
    pool: &sqlx::PgPool,
    token: &str,
    origin: &str,
) -> Result<u64, Box<dyn std::error::Error + Send + Sync>> {
    let origin = url::Url::parse(origin)?;
    if !matches!(origin.scheme(), "http" | "https") || origin.host_str().is_none() {
        return Err("WALLETS_WEB_ORIGIN must be an HTTP(S) origin".into());
    }
    let client = reqwest::Client::builder()
        .timeout(Duration::from_secs(10))
        .build()?;
    let owners: Vec<uuid::Uuid> = sqlx::query_scalar("SELECT user_id FROM finance_profiles")
        .fetch_all(pool)
        .await?;
    let mut sent = 0;
    for owner in owners {
        let mut tx = pool.begin().await?;
        let mut a = super::load(&mut tx, owner).await?;
        if !flag(&a.settings, "telegram_enabled")
            || text(&a.settings, "telegram_chat_id").is_empty()
        {
            continue;
        }
        let chat = text(&a.settings, "telegram_chat_id").to_owned();
        for reminder in reports::reminders(&a)?
            .iter()
            .filter(|r| number(r, "days_until") <= 0)
        {
            let kind = text(reminder, "kind");
            let id = number(reminder, "id");
            let previous = if kind == "loan" {
                a.loans
                    .iter()
                    .flat_map(|l| l["schedule"].as_array().into_iter().flatten())
                    .find(|r| number(r, "id") == id)
            } else {
                a.reminders.iter().find(|r| number(r, "id") == id)
            };
            if previous.is_some_and(|r| text(r, "reminded_on") == today().to_string()) {
                continue;
            }
            let url = origin.join(text(reminder, "pay_url"))?;
            let body = json!({"chat_id":chat,"text":format!("Nhắc hạn: {}\nSố tiền: {} ₫\nNgày đến hạn: {}",text(reminder,"name"),reminder["amount"],text(reminder,"due_date")),"reply_markup":{"inline_keyboard":[[{"text":"Ghi thanh toán","url":url.as_str()}]]}});
            let response = client
                .post(format!("https://api.telegram.org/bot{token}/sendMessage"))
                .json(&body)
                .send()
                .await;
            let success = match response {
                Ok(response) if response.status().is_success() => response
                    .json::<serde_json::Value>()
                    .await
                    .ok()
                    .is_some_and(|r| r["ok"] == true),
                _ => false,
            };
            if !success {
                tracing::warn!(%owner,reminder_id=id,"Wallets reminder delivery failed");
                continue;
            }
            let row = if kind == "loan" {
                a.loans
                    .iter_mut()
                    .flat_map(|l| l["schedule"].as_array_mut().into_iter().flatten())
                    .find(|r| number(r, "id") == id)
            } else {
                a.reminders.iter_mut().find(|r| number(r, "id") == id)
            };
            if let Some(row) = row {
                row["reminded_on"] = json!(today().to_string());
            }
            sent += 1;
        }
        super::save(&mut tx, owner, &a).await?;
        tx.commit().await?;
    }
    Ok(sent)
}
