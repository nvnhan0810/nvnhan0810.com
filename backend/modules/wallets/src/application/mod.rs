pub mod pages;
use crate::domain::{finance, lending, *};
use serde_json::{Value, json};

pub fn action(a: &mut Account, path: &str, method: &str, d: &Value) -> Result<Value> {
    let parts: Vec<_> = path.trim_matches('/').split('/').collect();
    let id = parts.get(1).and_then(|s| s.parse::<i64>().ok());
    let root = parts[0];
    let redirect = match (root, method) {
        ("wallets", "POST") if parts.get(1) == Some(&"sort") => {
            for item in d["wallets"].as_array().ok_or("Thứ tự không hợp lệ")? {
                let w = a.wallet_mut(number(item, "id"))?;
                w["is_pinned"] = json!(flag(item, "is_pinned"));
                w["order"] = json!(number(item, "order"));
            }
            "/wallets".into()
        }
        ("wallets", "POST" | "PUT") => {
            finance::save_wallet(
                a,
                if method == "PUT" {
                    Some(id.ok_or("Thiếu ví")?)
                } else {
                    None
                },
                d,
            )?;
            "/wallets".into()
        }
        ("wallets", "DELETE") => {
            let id = id.ok_or("Thiếu ví")?;
            a.wallet(id)?;
            if a.transactions.iter().any(|t| number(t, "wallet_id") == id)
                || a.loans.iter().any(|l| number(l, "wallet_id") == id)
            {
                return Err("Ví có giao dịch/khoản vay, hãy ngừng hoạt động thay vì xóa".into());
            }
            for template in &mut a.templates {
                for key in ["default_wallet_id", "from_wallet_id", "to_wallet_id"] {
                    if number(template, key) == id {
                        template[key] = Value::Null;
                    }
                }
            }
            if number(&a.goal, "wallet_id") == id {
                a.goal["wallet_id"] = Value::Null;
            }
            a.wallets.retain(|w| number(w, "id") != id);
            "/wallets".into()
        }
        ("transactions", "POST") => {
            for item in std::iter::once(d).chain(d["items"].as_array().into_iter().flatten()) {
                if number(item, "loan_id") > 0 || number(item, "payment_id") > 0 {
                    return Err("Ghi thanh toán qua chức năng khoản vay".into());
                }
            }

            if parts.get(1) == Some(&"bulk") {
                let items = d["items"]
                    .as_array()
                    .filter(|i| !i.is_empty() && i.len() <= 100)
                    .ok_or("Cần 1–100 giao dịch")?;
                for item in items {
                    let mut item = item.clone();
                    item["transacted_at"] = d["transacted_at"].clone();
                    finance::post(a, &item)?;
                }
            } else {
                finance::post(a, d)?;
            }
            "/transactions".into()
        }
        ("transactions", "DELETE") => {
            finance::reverse(a, id.ok_or("Thiếu giao dịch")?)?;
            "/transactions".into()
        }
        ("transaction-templates", "POST") => {
            finance::save_template(a, d)?;
            "/transaction-templates".into()
        }
        ("transaction-templates", "DELETE") => {
            let id = id.ok_or("Thiếu mẫu")?;
            if !a.templates.iter().any(|t| number(t, "id") == id) {
                return Err("Không tìm thấy mẫu".into());
            }
            for transaction in &mut a.transactions {
                if number(transaction, "transaction_template_id") == id {
                    transaction["transaction_template_id"] = Value::Null;
                }
            }
            a.templates.retain(|t| number(t, "id") != id);
            "/transaction-templates".into()
        }
        ("loans", "POST") if parts.get(1) == Some(&"preview") => {
            let schedule = lending::schedule(d, &a.holidays)?;
            let mut loan = d.clone();
            loan["started_at_label"] =
                json!(date(text(d, "started_at"))?.format("%d/%m/%Y").to_string());
            return Ok(
                json!({"component":"Loans/Preview","props":{"loan":loan,"schedule":schedule,"editable":true}}),
            );
        }
        ("loans", "POST") if parts.get(2) == Some(&"settle") => {
            lending::settle(a, id.ok_or("Thiếu khoản vay")?, d)?;
            "/loans".into()
        }
        ("loans", "POST") => {
            lending::create(a, d)?;
            "/loans".into()
        }
        ("loans", "PUT") => {
            required(d, "name")?;
            let wallet = number(d, "wallet_id");
            if wallet > 0 {
                a.wallet(wallet)?;
            }
            let l = a
                .loans
                .iter_mut()
                .find(|l| number(l, "id") == id.unwrap_or(0))
                .ok_or("Không tìm thấy khoản vay")?;
            l["name"] = d["name"].clone();
            l["wallet_id"] = d["wallet_id"].clone();
            format!("/loans/{}", id.unwrap())
        }
        ("payments", "POST") => {
            lending::payment(a, d)?;
            format!("/loans/{}", number(d, "loan_id"))
        }
        ("recurring-items", "POST" | "PUT") => {
            required(d, "name")?;
            positive(d, "amount")?;
            if !["income", "expense"].contains(&text(d, "type"))
                || !(1..=31).contains(&number(d, "day_of_month"))
            {
                return Err("Kỳ thu chi không hợp lệ".into());
            }
            let from = if text(d, "effective_from").is_empty() {
                today()
            } else {
                date(text(d, "effective_from"))?
            };
            if !text(d, "ends_at").is_empty() && date(text(d, "ends_at"))? < from {
                return Err("Ngày kết thúc trước ngày bắt đầu".into());
            }
            let mut r = d.clone();
            r["wallet_id"] = Value::Null;
            r["effective_from"] = json!(from.to_string());
            r["is_active"] = json!(d["is_active"].as_bool().unwrap_or(true));
            if method == "PUT" {
                let old = a
                    .recurring
                    .iter_mut()
                    .find(|r| number(r, "id") == id.unwrap_or(0))
                    .ok_or("Không tìm thấy khoản định kỳ")?;
                r["id"] = old["id"].clone();
                *old = r;
            } else {
                r["id"] = json!(a.id());
                a.recurring.push(r);
            }
            lending::generate_recurring(a)?;
            "/recurring-items".into()
        }
        ("recurring-items", "DELETE") => {
            if !a
                .recurring
                .iter()
                .any(|r| number(r, "id") == id.unwrap_or(0))
            {
                return Err("Không tìm thấy khoản định kỳ".into());
            }
            a.recurring.retain(|r| number(r, "id") != id.unwrap());
            a.reminders.retain(|r| {
                number(r, "recurring_item_id") != id.unwrap() || text(r, "status") == "posted"
            });
            for row in a.transactions.iter_mut().chain(&mut a.reminders) {
                if number(row, "recurring_item_id") == id.unwrap() {
                    row["recurring_item_id"] = Value::Null;
                }
            }
            "/recurring-items".into()
        }
        ("settings", "POST") => {
            if !(1..=30).contains(&number(d, "recurring_alert_days"))
                || text(d, "telegram_chat_id").len() > 64
            {
                return Err("Cài đặt không hợp lệ".into());
            }
            a.settings = d.clone();
            "/settings".into()
        }
        ("debt-goals", "POST") => {
            if flag(d, "clear") {
                a.goal = Value::Null;
            } else {
                let w = a.wallet(number(d, "wallet_id"))?;
                if text(w, "type") == "credit_card" || !flag(w, "is_active") {
                    return Err("Mục tiêu cần ví tiết kiệm đang hoạt động".into());
                }
                let loan = number(d, "loan_id");
                if loan > 0
                    && !a.loans.iter().any(|l| {
                        number(l, "id") == loan
                            && text(l, "type") == "bank"
                            && !flag(l, "is_settled")
                    })
                {
                    return Err("Khoản vay mục tiêu không hợp lệ".into());
                }
                if loan == 0 {
                    positive(d, "target_amount")?;
                }
                let mut years = std::collections::HashSet::new();
                for fee in d["settlement_fees"].as_array().into_iter().flatten() {
                    let year = number(fee, "year");
                    if !(1..=40).contains(&year)
                        || !years.insert(year)
                        || !["fixed", "percent"].contains(&text(fee, "type"))
                        || cents(fee, "value")? < 0
                    {
                        return Err("Biểu phí không hợp lệ".into());
                    }
                }
                a.goal = d.clone();
            }
            "/debt-goals".into()
        }
        ("holidays", "POST") => {
            let rows = if parts.get(1) == Some(&"import") {
                d["rows"]
                    .as_array()
                    .filter(|rows| rows.len() <= 10000)
                    .ok_or("Dữ liệu import không hợp lệ")?
                    .clone()
            } else {
                vec![d.clone()]
            };
            for mut row in rows {
                let day = date(text(&row, "date"))?;
                required(&row, "name")?;
                if !["public", "bank", "custom"].contains(&text(&row, "type")) {
                    return Err("Loại ngày lễ không hợp lệ".into());
                }
                row["date"] = json!(day.to_string());
                if let Some(old) = a.holidays.iter_mut().find(|h| h["date"] == row["date"]) {
                    row["id"] = old["id"].clone();
                    *old = row;
                } else {
                    row["id"] = json!(a.id());
                    a.holidays.push(row);
                }
            }
            recalculate(a)?;
            "/holidays".into()
        }
        ("holidays", "DELETE") => {
            a.holidays.retain(|h| number(h, "id") != id.unwrap_or(0));
            recalculate(a)?;
            "/holidays".into()
        }
        _ => return Err("Không tìm thấy thao tác".into()),
    };
    Ok(json!({"redirect":redirect,"flash":{"success":"Đã lưu thay đổi."}}))
}
fn recalculate(a: &mut Account) -> Result<()> {
    for loan in &mut a.loans {
        if !flag(loan, "is_settled") && text(loan, "interest_calculation_method") == "daily" {
            let mut input = loan.clone();
            input["custom_schedule"] = Value::Null;
            let fresh = lending::schedule(&input, &a.holidays)?;
            for (old, new) in loan["schedule"]
                .as_array_mut()
                .into_iter()
                .flatten()
                .zip(fresh)
            {
                if !flag(old, "is_paid") {
                    let id = old["id"].clone();
                    *old = new;
                    old["id"] = id;
                }
            }
        }
    }
    Ok(())
}
