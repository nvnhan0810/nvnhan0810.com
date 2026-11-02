use super::*;
use chrono::Duration;
pub fn progress(a: &Account) -> Value {
    if number(&a.goal, "wallet_id") == 0 {
        return Value::Null;
    }
    let wallet = match a.wallet(number(&a.goal, "wallet_id")) {
        Ok(w) => w,
        Err(_) => return Value::Null,
    };
    let loan = a
        .loans
        .iter()
        .find(|l| number(l, "id") == number(&a.goal, "loan_id"));
    let principal = loan.map(lending::remaining).unwrap_or(0);
    let next = loan
        .and_then(|l| l["schedule"].as_array())
        .and_then(|rs| rs.iter().find(|r| !flag(r, "is_paid")));
    let interest = next.and_then(|r| cents(r, "interest").ok()).unwrap_or(0);
    let year = next
        .map(|r| (number(r, "month_index") + 11) / 12)
        .unwrap_or(1);
    let fee = settlement_fee(&a.goal, year, principal);
    let target = if loan.is_some() {
        principal + interest + fee
    } else {
        cents(&a.goal, "target_amount").unwrap_or(0)
    };
    let collected = cents(wallet, "balance").unwrap_or(0).max(0);
    json!({"name":a.goal["name"],"target_amount":money(target),"collected":money(collected),"percent":if target>0{((collected as f64/target as f64*1000.0).round()/10.0).min(100.0)}else{0.0},"remaining":money((target-collected).max(0)),"wallet_id":wallet["id"],"wallet_name":wallet["name"],"reached":target>0&&collected>=target,"loan_id":a.goal["loan_id"],"loan_name":loan.map(|l|l["name"].clone()),"has_loan":loan.is_some(),"principal_remaining":money(principal),"settlement_interest":money(interest),"settlement_fee":money(fee),"current_year":year})
}
pub fn settlement_fee(goal: &Value, year: i64, principal: i64) -> i64 {
    let rule = goal["settlement_fees"].as_array().and_then(|rs| {
        rs.iter()
            .filter(|r| number(r, "year") <= year)
            .max_by_key(|r| number(r, "year"))
    });
    rule.map(|r| {
        if text(r, "type") == "fixed" {
            cents(r, "value").unwrap_or(0)
        } else {
            (principal as f64
                * r["value"]
                    .as_f64()
                    .or_else(|| text(r, "value").parse().ok())
                    .unwrap_or(0.0)
                / 100.0)
                .round() as i64
        }
    })
    .unwrap_or(0)
}
pub fn cashflow(a: &Account, period: &str) -> Value {
    let (period, count, label, range) = match period {
        "day" => ("day", 14, "Ngày", "14 ngày gần nhất"),
        "week" => ("week", 8, "Tuần", "8 tuần gần nhất"),
        "year" => ("year", 5, "Năm", "5 năm gần nhất"),
        _ => ("month", 6, "Tháng", "6 tháng gần nhất"),
    };
    let now = today();
    let current = match period {
        "day" => now,
        "week" => now - Duration::days(now.weekday().num_days_from_monday() as i64),
        "year" => month_day(now.year(), 1, 1),
        _ => month_day(now.year(), now.month(), 1),
    };
    let mut series = Vec::new();
    let mut has_data = false;
    for offset in (0..count).rev() {
        let start = match period {
            "day" => current - Duration::days(offset),
            "week" => current - Duration::weeks(offset),
            "year" => month_day(current.year() - offset as i32, 1, 1),
            _ => {
                let total = current.year() * 12 + current.month0() as i32 - offset as i32;
                month_day(total / 12, (total % 12 + 1) as u32, 1)
            }
        };
        let until = match period {
            "day" => start + Duration::days(1),
            "week" => start + Duration::weeks(1),
            "year" => month_day(start.year() + 1, 1, 1),
            _ => add_months(start, 1, 1),
        };
        let (mut income, mut expense) = (0, 0);
        for tx in &a.transactions {
            if number(tx, "wallet_transfer_id") > 0 || text(tx, "type") == "adjustment" {
                continue;
            }
            let Ok(d) = date(text(tx, "transacted_at")) else {
                continue;
            };
            if d < start || d >= until {
                continue;
            }
            has_data = true;
            let amount = cents(tx, "amount").unwrap_or(0);
            if text(tx, "type") == "income" {
                income += amount
            } else {
                expense += amount
            }
        }
        let display = match period {
            "year" => start.format("%Y").to_string(),
            "month" => start.format("%m/%Y").to_string(),
            _ => start.format("%d/%m").to_string(),
        };
        series.push(json!({"label":display,"label_full":format!("{} – {}",start.format("%d/%m/%Y"),(until-Duration::days(1)).format("%d/%m/%Y")),"income":money(income),"expense":money(expense),"net":money(income-expense)}));
    }
    let current = series.last().cloned().unwrap_or(json!({}));
    let previous = series
        .get(series.len().saturating_sub(2))
        .cloned()
        .unwrap_or(json!({}));
    let change = |key| {
        let prev = cents(&previous, key).unwrap_or(0);
        let next = cents(&current, key).unwrap_or(0);
        if prev == 0 {
            Value::Null
        } else {
            json!(((next - prev) as f64 / prev as f64 * 1000.0).round() / 10.0)
        }
    };
    json!({"has_data":has_data,"period":period,"period_label":label,"range_label":range,"current":current,"previous":previous,"income_change":change("income"),"expense_change":change("expense"),"expense_vs_prev":change("expense"),"series":series})
}
pub fn reminders(a: &Account) -> Result<Vec<Value>> {
    let end = today() + Duration::days(number(&a.settings, "recurring_alert_days").clamp(1, 30));
    let mut out = Vec::new();
    for r in a
        .reminders
        .iter()
        .filter(|r| !["posted", "skipped"].contains(&text(r, "status")))
    {
        let due = date(text(r, "due_date"))?;
        if due > end {
            continue;
        }
        let mut item = r.clone();
        let mut query = url::form_urlencoded::Serializer::new(String::new());
        for (key, value) in [
            ("type", r["type"].clone()),
            ("amount", r["expected_amount"].clone()),
            ("description", r["name"].clone()),
            ("transacted_at", r["due_date"].clone()),
            ("recurring_item_id", r["recurring_item_id"].clone()),
            ("recurring_occurrence_id", r["id"].clone()),
        ] {
            query.append_pair(
                key,
                &value
                    .as_str()
                    .map(str::to_owned)
                    .unwrap_or(value.to_string()),
            );
        }
        item["pay_url"] = json!(format!("/transactions/create?{}", query.finish()));
        item["kind"] = json!("recurring");
        item["days_until"] = json!((due - today()).num_days());
        item["type_label"] = json!(if text(r, "type") == "income" {
            "Thu cố định"
        } else {
            "Chi cố định"
        });
        item["type_badge_class"] = json!("bg-teal-100 text-teal-800");
        item["insufficient_funds"] = json!(false);
        item["recurring_occurrence_id"] = r["id"].clone();
        out.push(item);
    }
    for loan in a
        .loans
        .iter()
        .filter(|l| !flag(l, "is_settled") && text(l, "type") == "bank")
    {
        for r in loan["schedule"]
            .as_array()
            .into_iter()
            .flatten()
            .filter(|r| !flag(r, "is_paid"))
        {
            let due = date(text(r, "due_date"))?;
            if due > end {
                continue;
            }
            out.push(json!({"kind":"loan","id":r["id"],"loan_id":loan["id"],"period_id":r["id"],"name":loan["name"],"amount":r["payment"],"due_date":r["due_date"],"days_until":(due-today()).num_days(),"type_label":"Trả khoản vay","type_badge_class":"bg-amber-100 text-amber-800","insufficient_funds":false,"pay_url":format!("/loans?pay={}&period={}&amount={}",number(loan,"id"),number(r,"id"),r["payment"])}));
        }
    }
    out.sort_by_key(|r| text(r, "due_date").to_owned());
    Ok(out)
}
pub fn fixed(a: &Account, q: &Value) -> Result<Value> {
    let g = if ["week", "month", "year", "custom"].contains(&text(q, "granularity")) {
        text(q, "granularity")
    } else {
        "month"
    };
    let normalize = |d: NaiveDate| match g {
        "month" => month_day(d.year(), d.month(), 1),
        "year" => month_day(d.year(), 1, 1),
        "week" => d - Duration::days(d.weekday().num_days_from_monday() as i64),
        _ => d,
    };
    let min = normalize(today());
    let start = date(text(q, "start"))
        .map(normalize)
        .unwrap_or(min)
        .max(min);
    let duration = if q["duration"].is_null() {
        5
    } else {
        number(q, "duration").clamp(1, 24)
    };
    let custom = if q["custom_days"].is_null() {
        30
    } else {
        number(q, "custom_days").clamp(1, 366)
    };
    let mut periods = Vec::new();
    let mut grand = 0;
    let mut from = start;
    for idx in 0..duration {
        let until = match g {
            "month" => add_months(from, 1, 1),
            "year" => month_day(from.year() + 1, 1, 1),
            "week" => from + Duration::days(7),
            _ => from + Duration::days(custom),
        };
        let mut items = Vec::new();
        let mut total = 0;
        for loan in a
            .loans
            .iter()
            .filter(|l| !flag(l, "is_settled") && text(l, "type") != "lend")
        {
            for r in loan["schedule"]
                .as_array()
                .into_iter()
                .flatten()
                .filter(|r| !flag(r, "is_paid"))
            {
                let due = date(text(r, "due_date"))?;
                if due >= from && due < until {
                    let amt = cents(r, "payment")?;
                    total += amt;
                    items.push(json!({"source":"loan","source_id":loan["id"],"name":loan["name"],"amount":money(amt),"due_date":due.to_string(),"type_label":"Trả nợ"}));
                }
            }
        }
        for r in a
            .recurring
            .iter()
            .filter(|r| flag(r, "is_active") && text(r, "type") == "expense")
        {
            let mut month = month_day(from.year(), from.month(), 1);
            while month < until {
                let due = month_day(
                    month.year(),
                    month.month(),
                    number(r, "day_of_month") as u32,
                );
                if due >= from
                    && due < until
                    && (text(r, "effective_from").is_empty()
                        || due >= date(text(r, "effective_from"))?)
                    && (text(r, "ends_at").is_empty() || due <= date(text(r, "ends_at"))?)
                {
                    let amt = cents(r, "amount")?;
                    total += amt;
                    items.push(json!({"source":"recurring","source_id":r["id"],"name":r["name"],"amount":money(amt),"due_date":due.to_string(),"type_label":"Chi cố định"}));
                }
                month = add_months(month, 1, 1);
            }
        }
        grand += total;
        periods.push(json!({"index":idx,"key":from.to_string(),"label":format!("{} – {}",from.format("%d/%m/%Y"),(until-Duration::days(1)).format("%d/%m/%Y")),"start":from.to_string(),"end":(until-Duration::days(1)).to_string(),"total":money(total),"items":items}));
        from = until;
    }
    Ok(
        json!({"granularity":g,"start":start.to_string(),"duration":duration,"custom_days":custom,"min_start":min.to_string(),"periods":periods,"grand_total":money(grand),"granularity_options":[{"value":"week","label":"Tuần"},{"value":"month","label":"Tháng"},{"value":"year","label":"Năm"},{"value":"custom","label":"Tùy chọn"}]}),
    )
}
