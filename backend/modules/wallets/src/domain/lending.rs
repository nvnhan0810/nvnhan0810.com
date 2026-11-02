use super::*;
use chrono::{Duration, Weekday};

fn value(v: &Value, k: &str) -> f64 {
    v[k].as_f64()
        .or_else(|| v[k].as_str().and_then(|s| s.parse().ok()))
        .unwrap_or(0.0)
}
pub fn schedule(d: &Value, holidays: &[Value]) -> Result<Vec<Value>> {
    let principal = positive(d, "principal_amount")?;
    let start = date(text(d, "started_at"))?;
    let months = number(d, "term_months");
    if !(1..=600).contains(&months) {
        return Err("Kỳ hạn phải từ 1 đến 600 tháng".into());
    }
    let method = text(d, "interest_calculation_method");
    if !["monthly", "daily", "homecredit", "custom", ""].contains(&method) {
        return Err("Cách tính lãi không hợp lệ".into());
    }
    let annual = value(d, "interest_rate") / 100.0;
    if !annual.is_finite() || !(0.0..=10.0).contains(&annual) {
        return Err("Lãi suất không hợp lệ".into());
    }
    let rate = annual / 12.0;
    let configured = cents(d, "monthly_payment")?;
    if configured < 0 {
        return Err("Tiền trả mỗi kỳ không hợp lệ".into());
    }
    let payment = if configured > 0 {
        configured
    } else if rate == 0.0 {
        principal / months
    } else {
        (principal as f64 * rate / (1.0 - (1.0 + rate).powi(-months as i32))).round() as i64
    };
    let fee = cents(d, "collection_fee")?;
    if fee < 0 {
        return Err("Phí không hợp lệ".into());
    }
    let day = if number(d, "payment_day") > 0 {
        number(d, "payment_day") as u32
    } else {
        start.day()
    };
    if day > 31 {
        return Err("Ngày trả không hợp lệ".into());
    }
    let mut remaining = principal;
    let mut previous = start;
    let mut rows = Vec::new();
    let custom = d["custom_schedule"].as_array();
    if method == "custom" && custom.is_none_or(Vec::is_empty) {
        return Err("Cần nhập lịch trả nợ".into());
    }
    if let Some(custom) = custom.filter(|rows| !rows.is_empty()) {
        if custom.len() != months as usize {
            return Err("Lịch trả phải đủ số kỳ".into());
        }
        for (i, row) in custom.iter().enumerate() {
            let due = date(if text(row, "paid_at").is_empty() {
                text(row, "due_date")
            } else {
                text(row, "paid_at")
            })?;
            let p = cents(row, "principal")?;
            let interest = cents(row, "interest")?;
            let f = cents(row, "fee")?;
            let pay = cents(row, "payment")?;
            if p < 0
                || interest < 0
                || f < 0
                || p > remaining
                || p + interest + f != pay
                || due <= previous
                || (i + 1 < custom.len() && configured > 0 && pay > configured)
            {
                return Err("Lịch trả không cân bằng hoặc sai thứ tự".into());
            }
            remaining -= p;
            rows.push(json!({"id":0,"month_index":i+1,"due_date":due.to_string(),"due_date_label":due.format("%d/%m/%Y").to_string(),"days":(due-previous).num_days(),"principal":money(p),"interest":money(interest),"fee":money(f),"payment":money(pay),"remaining_principal":money(remaining),"is_paid":false,"status":"pending"}));
            previous = due;
        }
        if remaining != 0 {
            return Err("Lịch trả chưa tất toán hết gốc".into());
        }
        return Ok(rows);
    }
    for i in 1..=months {
        let mut due = add_months(start, i as u32, day);
        if method == "daily" {
            if i == 1 {
                due = due.max(start + Duration::days(33));
            }
            while matches!(due.weekday(), Weekday::Sat | Weekday::Sun)
                || holidays.iter().any(|h| text(h, "date") == due.to_string())
            {
                due = due
                    .checked_add_signed(Duration::days(1))
                    .ok_or("Ngày trả vượt giới hạn")?;
            }
        }
        let days = (due - previous).num_days().max(0);
        let interest = if method == "daily" || method == "homecredit" {
            let raw = remaining as f64 * annual * days as f64 / 365.0;
            if method == "homecredit" {
                (raw / 1000.0).ceil() as i64 * 1000
            } else {
                (raw / 100.0).round() as i64 * 100
            }
        } else {
            (remaining as f64 * rate / 100.0).round() as i64 * 100
        };
        let f = if method == "homecredit" { fee } else { 0 };
        let principal_part = if i == months {
            remaining
        } else {
            (payment - interest - f).max(0).min(remaining)
        };
        if principal_part == 0 && remaining > 0 && i < months {
            return Err("Tiền trả mỗi kỳ không đủ trả lãi/phí".into());
        }
        remaining -= principal_part;
        rows.push(json!({"id":0,"month_index":i,"due_date":due.to_string(),"due_date_label":due.format("%d/%m/%Y").to_string(),"days":days,"principal":money(principal_part),"interest":money(interest),"fee":money(f),"payment":money(principal_part+interest+f),"remaining_principal":money(remaining),"status":"pending","is_paid":false}));
        previous = due;
    }
    Ok(rows)
}
pub fn create(a: &mut Account, d: &Value) -> Result<i64> {
    required(d, "name")?;
    positive(d, "principal_amount")?;
    let kind = text(d, "type");
    if !["bank", "borrow", "lend"].contains(&kind) {
        return Err("Loại khoản vay không hợp lệ".into());
    }
    let start = date(text(d, "started_at"))?;
    if number(d, "months_paid") < 0
        || (kind == "bank" && number(d, "months_paid") > number(d, "term_months"))
    {
        return Err("Số kỳ đã trả không hợp lệ".into());
    }
    let wallet = number(d, "wallet_id");
    if wallet > 0 {
        a.wallet(wallet)?;
    }
    let mut rows = if kind == "bank" {
        schedule(d, &a.holidays)?
    } else {
        Vec::new()
    };
    for row in &mut rows {
        row["id"] = json!(a.id());
        if number(row, "month_index") <= number(d, "months_paid")
            || date(text(row, "due_date"))? < today()
        {
            row["is_paid"] = json!(true);
            row["status"] = json!("paid");
        }
    }
    let id = a.id();
    let mut loan = d.clone();
    loan["id"] = json!(id);
    loan["started_at"] = json!(start.to_string());
    loan["schedule"] = json!(rows);
    loan["payments"] = json!([]);
    loan["is_settled"] = json!(false);
    loan["remaining_principal"] = d["principal_amount"].clone();
    if start == today() && flag(d, "record_cash_flow") {
        if wallet == 0 {
            return Err("Chọn ví nhận/chi tiền".into());
        }
        let mut tx = json!({"type":if kind=="lend"{"expense"}else{"income"},"wallet_id":wallet,"amount":d["principal_amount"],"transacted_at":start.to_string(),"description":format!("Khoản vay: {}",text(d,"name")),"loan_id":id});
        if !d["received_amount"].is_null() && d["received_amount"] != "" {
            tx["amount"] = d["received_amount"].clone();
        }
        finance::post(a, &tx)?;
    }
    a.loans.push(loan);
    Ok(id)
}
pub fn remaining(loan: &Value) -> i64 {
    if flag(loan, "is_settled") {
        return 0;
    }
    let initial = cents(loan, "principal_amount").unwrap_or(0);
    if text(loan, "type") == "bank" {
        let mut remaining = initial;
        for r in loan["schedule"].as_array().into_iter().flatten() {
            if flag(r, "is_paid") || text(r, "due_date") < today().to_string().as_str() {
                remaining = cents(r, "remaining_principal").unwrap_or(remaining);
            }
        }
        remaining
    } else {
        let paid: i64 = loan["payments"]
            .as_array()
            .into_iter()
            .flatten()
            .map(|p| cents(p, "amount").unwrap_or(0))
            .sum();
        (initial - paid).max(0)
    }
}
pub fn payment(a: &mut Account, d: &Value) -> Result<()> {
    let id = number(d, "loan_id");
    let idx = a
        .loans
        .iter()
        .position(|l| number(l, "id") == id && !flag(l, "is_settled"))
        .ok_or("Khoản vay không tồn tại hoặc đã tất toán")?;
    let amount = positive(d, "amount")?;
    let paid = date(text(d, "paid_at"))?;
    let mut loan = a.loans[idx].clone();
    let wallet = number(d, "wallet_id");
    a.wallet(wallet)?;
    let pid = a.id();
    let mut p = json!({"id":pid,"amount":money(amount),"paid_at":paid.format("%d/%m/%Y").to_string(),"note":d["note"],"kind":"normal","kind_label":"Đúng kỳ","is_early":false,"reduces_principal":true});
    if text(&loan, "type") == "bank" {
        let requested = number(d, "period_id");
        let rows = loan["schedule"].as_array_mut().ok_or("Thiếu lịch trả")?;
        let pos = if requested > 0 {
            rows.iter()
                .position(|r| number(r, "id") == requested && !flag(r, "is_paid"))
        } else {
            rows.iter().position(|r| !flag(r, "is_paid"))
        }
        .ok_or("Kỳ trả không tồn tại hoặc đã được thanh toán")?;
        let r = &mut rows[pos];
        let early = paid < date(text(r, "due_date"))?;
        r["is_paid"] = json!(true);
        r["status"] = json!("paid");
        r["payment_id"] = json!(pid);
        p["period_id"] = r["id"].clone();
        p["period_due_date"] = r["due_date"].clone();
        p["month_index"] = r["month_index"].clone();
        p["is_early"] = json!(early);
        p["reduces_principal"] = json!(!early && paid >= date("2026-06-01")?);
        p["kind"] = json!(if early { "early" } else { "normal" });
        p["kind_label"] = json!(if early {
            "Trả trước"
        } else {
            "Đúng kỳ"
        });
    }
    finance::post(
        a,
        &json!({"type":if text(&loan,"type")=="lend"{"income"}else{"expense"},"amount":money(amount),"wallet_id":wallet,"transacted_at":paid.to_string(),"description":format!("Thanh toán: {}",text(&loan,"name")),"note":d["note"],"loan_id":id,"payment_id":pid}),
    )?;
    loan["payments"]
        .as_array_mut()
        .ok_or("Thiếu lịch sử thanh toán")?
        .push(p);
    if number(&loan, "wallet_id") == 0 {
        loan["wallet_id"] = json!(wallet);
    }
    loan["remaining_principal"] = money(remaining(&loan));
    a.loans[idx] = loan;
    Ok(())
}
pub fn settle(a: &mut Account, id: i64, d: &Value) -> Result<()> {
    let idx = a
        .loans
        .iter()
        .position(|l| number(l, "id") == id && !flag(l, "is_settled"))
        .ok_or("Không tìm thấy khoản vay")?;
    let loan = a.loans[idx].clone();
    let amount = if d["amount"].is_null() || text(d, "amount").is_empty() && d["amount"].is_string()
    {
        remaining(&loan)
    } else {
        cents(d, "amount")?
    };
    if amount < 0 {
        return Err("Tiền tất toán không hợp lệ".into());
    }
    if amount > 0 {
        let pid = a.id();
        let day = if text(d, "paid_at").is_empty() {
            today()
        } else {
            date(text(d, "paid_at"))?
        };
        finance::post(
            a,
            &json!({"type":if text(&loan,"type")=="lend"{"income"}else{"expense"},"amount":money(amount),"wallet_id":d["wallet_id"],"transacted_at":if text(d,"paid_at").is_empty(){today().to_string()}else{date(text(d,"paid_at"))?.to_string()},"description":format!("Tất toán: {}",text(&loan,"name")),"loan_id":id,"payment_id":pid,"note":d["note"]}),
        )?;
        a.loans[idx]["payments"].as_array_mut().ok_or("Thiếu lịch sử thanh toán")?.push(json!({"id":pid,"amount":money(amount),"paid_at":day.format("%d/%m/%Y").to_string(),"note":d["note"],"kind":"settlement","kind_label":"Tất toán","reduces_principal":true,"is_early":false}));
    }
    a.loans[idx]["is_settled"] = json!(true);
    a.loans[idx]["remaining_principal"] = json!(0);
    Ok(())
}
pub fn generate_recurring(a: &mut Account) -> Result<()> {
    let now = today();
    let end = now + Duration::days(30);
    let items = a.recurring.clone();
    for item in items.iter().filter(|r| flag(r, "is_active")) {
        for n in 0..=2 {
            let due = add_months(now, n, number(item, "day_of_month") as u32);
            if due < now
                || due > end
                || (!text(item, "effective_from").is_empty()
                    && due < date(text(item, "effective_from"))?)
                || (!text(item, "ends_at").is_empty() && due > date(text(item, "ends_at"))?)
            {
                continue;
            }
            if !a.reminders.iter().any(|r| {
                r["recurring_item_id"] == item["id"] && text(r, "due_date") == due.to_string()
            }) {
                let id = a.id();
                a.reminders.push(json!({"id":id,"recurring_item_id":item["id"],"name":item["name"],"type":item["type"],"amount":item["amount"],"expected_amount":item["amount"],"due_date":due.to_string(),"status":"pending","wallet_id":item["wallet_id"]}));
            }
        }
    }
    for r in &mut a.reminders {
        if !["posted", "skipped"].contains(&text(r, "status")) {
            r["status"] = json!(if text(r, "due_date") < now.to_string().as_str() {
                "overdue"
            } else if text(r, "due_date") == now.to_string() {
                "due"
            } else {
                "pending"
            });
        }
    }
    Ok(())
}
