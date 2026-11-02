use super::*;

pub fn wallet_types() -> Value {
    json!({"cash":"Tiền mặt","bank":"Tài khoản ngân hàng","credit_card":"Thẻ tín dụng","e_wallet":"Ví điện tử"})
}
pub fn wallet_view(w: &Value) -> Value {
    let mut w = w.clone();
    w["is_credit_card"] = json!(text(&w, "type") == "credit_card");
    w["type_label"] = wallet_types()[text(&w, "type")].clone();
    w["spendable_balance"] = w["balance"].clone();
    w
}
pub fn save_wallet(a: &mut Account, id: Option<i64>, d: &Value) -> Result<()> {
    let name = required(d, "name")?;
    let kind = required(d, "type")?;
    if wallet_types().get(&kind).is_none() {
        return Err("Loại ví không hợp lệ".into());
    }
    let mut w = match id {
        Some(id) => a.wallet(id)?.clone(),
        None => {
            json!({"id":a.id(),"balance":0,"is_pinned":false,"order":a.wallets.len(),"is_active":true})
        }
    };
    if kind == "credit_card" {
        let limit = cents(d, "credit_limit")?;
        if limit < 0
            || !(1..=31).contains(&number(d, "statement_day"))
            || !(1..=31).contains(&number(d, "payment_day"))
        {
            return Err("Hạn mức/ngày sao kê không hợp lệ".into());
        }
        let debt = if id.is_some() {
            cents(&w, "outstanding_balance")?
        } else {
            cents(d, "outstanding_balance")?
        };
        if (id.is_none() && debt < 0) || debt > limit {
            return Err("Dư nợ vượt hạn mức".into());
        }
        w["credit_limit"] = money(limit);
        w["outstanding_balance"] = money(debt);
        w["balance"] = money((limit - debt).max(0));
        w["statement_day"] = d["statement_day"].clone();
        w["payment_day"] = d["payment_day"].clone();
    } else if id.is_none() {
        w["balance"] = money(cents(d, "balance")?);
    } else {
        w["credit_limit"] = Value::Null;
        w["outstanding_balance"] = Value::Null;
    }
    w["name"] = json!(name);
    w["type"] = json!(kind);
    w["notes"] = d["notes"].clone();
    if id.is_some() {
        w["is_active"] = json!(flag(d, "is_active"));
    }
    if let Some(id) = id {
        *a.wallet_mut(id)? = w;
    } else {
        a.wallets.push(w);
    }
    Ok(())
}
fn apply(a: &mut Account, wallet: i64, delta: i64) -> Result<()> {
    let w = a.wallet_mut(wallet)?;
    if text(w, "type") == "credit_card" {
        let debt = cents(w, "outstanding_balance")? - delta;
        w["outstanding_balance"] = money(debt);
        w["balance"] = money((cents(w, "credit_limit")? - debt).max(0));
    } else {
        w["balance"] = money(
            cents(w, "balance")?
                .checked_add(delta)
                .ok_or("Số dư vượt giới hạn")?,
        );
    }
    Ok(())
}
pub fn post(a: &mut Account, d: &Value) -> Result<i64> {
    let kind = required(d, "type")?;
    let desc = required(d, "description")?;
    let day = date(text(d, "transacted_at"))?.to_string();
    for key in ["transaction_template_id", "recurring_item_id"] {
        let id = number(d, key);
        if id > 0 {
            let list = if key == "transaction_template_id" {
                &a.templates
            } else {
                &a.recurring
            };
            if !list.iter().any(|v| number(v, "id") == id) {
                return Err("Tham chiếu không thuộc tài khoản".into());
            }
        }
    }
    let transfer = if kind == "transfer" {
        Some(a.id())
    } else {
        None
    };
    let mut entries = Vec::new();
    match kind.as_str() {
        "transfer" => {
            let from = number(d, "from_wallet_id");
            let to = number(d, "to_wallet_id");
            a.wallet(from)?;
            a.wallet(to)?;
            if from == to {
                return Err("Ví nguồn và đích phải khác nhau".into());
            }
            let amount = positive(d, "amount")?;
            let fee = cents(d, "fee")?;
            if fee < 0 {
                return Err("Phí không hợp lệ".into());
            }
            entries.push((from, -amount - fee, "expense"));
            entries.push((to, amount, "income"));
        }
        "income" | "expense" => {
            let amount = positive(d, "amount")?;
            entries.push((
                number(d, "wallet_id"),
                if kind == "income" { amount } else { -amount },
                kind.as_str(),
            ));
        }
        "adjustment" => {
            let id = number(d, "wallet_id");
            let target = cents(d, "target_balance")?;
            if target < 0 {
                return Err("Số dư đích không hợp lệ".into());
            }
            let delta = target - cents(a.wallet(id)?, "balance")?;
            if delta == 0 {
                return Err("Số dư không thay đổi".into());
            }
            entries.push((id, delta, "adjustment"));
        }
        _ => return Err("Loại giao dịch không hợp lệ".into()),
    }
    let occurrence = number(d, "recurring_occurrence_id");
    if occurrence > 0 {
        let r = a
            .reminders
            .iter()
            .find(|r| number(r, "id") == occurrence && text(r, "status") != "posted")
            .ok_or("Kỳ thu chi đã được ghi nhận hoặc không tồn tại")?;
        if text(r, "type") != kind
            || number(d, "recurring_item_id") != number(r, "recurring_item_id")
        {
            return Err("Loại giao dịch không khớp kỳ thu chi".into());
        }
    }
    let mut last = 0;
    for (wallet, delta, entry_type) in entries {
        a.wallet(wallet)?;
        let before = a.wallet(wallet)?.clone();
        apply(a, wallet, delta)?;
        let after = a.wallet(wallet)?.clone();
        last = a.id();
        let wname = text(&before, "name").to_owned();
        let tx = json!({"id":last,"wallet_id":wallet,"wallet":{"id":wallet,"name":wname},"wallet_name":wname,"type":entry_type,"type_label":if entry_type=="income"{"Thu"}else if entry_type=="expense"{"Chi"}else{"Cân đối"},"amount":money(delta.abs()),"delta":delta,"description":desc,"category":if transfer.is_some(){json!("Chuyển ví")}else{d["category"].clone()},"transacted_at":day,"note":d["note"],"loan_id":d["loan_id"],"payment_id":d["payment_id"],"wallet_transfer_id":transfer,"transaction_template_id":d["transaction_template_id"],"recurring_occurrence_id":occurrence,"recurring_item_id":d["recurring_item_id"],"adjustment_direction":if delta>0{"increase"}else{"decrease"},"balance_before":before["balance"],"balance_after":after["balance"],"debt_before":before["outstanding_balance"],"debt_after":after["outstanding_balance"]});
        a.transactions.push(tx);
    }
    if occurrence > 0 {
        let r = a
            .reminders
            .iter_mut()
            .find(|r| number(r, "id") == occurrence)
            .unwrap();
        r["status"] = json!("posted");
        r["transaction_id"] = json!(last);
    }
    if flag(d, "save_as_template") {
        let mut template = d.clone();
        template["name"] = if text(d, "template_name").trim().is_empty() {
            json!(desc)
        } else {
            d["template_name"].clone()
        };
        if kind == "adjustment" {
            let tx = a.transactions.last().ok_or("Thiếu giao dịch cân đối")?;
            template["amount"] = tx["amount"].clone();
            template["adjustment_direction"] = tx["adjustment_direction"].clone();
        }
        template["default_wallet_id"] = d["wallet_id"].clone();
        save_template(a, &template)?;
    }
    Ok(last)
}
pub fn reverse(a: &mut Account, id: i64) -> Result<()> {
    let tx = a
        .transactions
        .iter()
        .find(|t| number(t, "id") == id)
        .ok_or("Không tìm thấy giao dịch")?
        .clone();
    if number(&tx, "loan_id") > 0 {
        return Err("Giao dịch gắn khoản vay không được xóa tại đây".into());
    }
    let transfer = number(&tx, "wallet_transfer_id");
    let entries: Vec<_> = a
        .transactions
        .iter()
        .filter(|t| {
            number(t, "id") == id || (transfer > 0 && number(t, "wallet_transfer_id") == transfer)
        })
        .cloned()
        .collect();
    for t in &entries {
        let wallet = number(t, "wallet_id");
        apply(a, wallet, -number(t, "delta"))?;
        if number(t, "recurring_occurrence_id") > 0
            && let Some(r) = a
                .reminders
                .iter_mut()
                .find(|r| number(r, "id") == number(t, "recurring_occurrence_id"))
        {
            r["status"] = json!("pending");
            r["transaction_id"] = Value::Null;
        }
    }
    a.transactions
        .retain(|t| !entries.iter().any(|e| e["id"] == t["id"]));
    Ok(())
}
pub fn save_template(a: &mut Account, d: &Value) -> Result<()> {
    required(d, "name")?;
    positive(d, "amount")?;
    if !["income", "expense", "transfer", "adjustment"].contains(&text(d, "type")) {
        return Err("Loại mẫu không hợp lệ".into());
    }
    for key in ["default_wallet_id", "from_wallet_id", "to_wallet_id"] {
        let id = number(d, key);
        if id > 0 {
            a.wallet(id)?;
        }
    }
    if text(d, "type") == "transfer"
        && (number(d, "from_wallet_id") == number(d, "to_wallet_id")
            || number(d, "from_wallet_id") == 0
            || number(d, "to_wallet_id") == 0)
    {
        return Err("Ví chuyển tiền không hợp lệ".into());
    }
    let mut t = d.clone();
    t["id"] = json!(a.id());
    t["type_label"] = json!(match text(d, "type") {
        "income" => "Thu",
        "expense" => "Chi",
        "transfer" => "Chuyển ví",
        _ => "Cân đối",
    });
    a.templates.push(t);
    Ok(())
}

pub fn transaction_view(a: &Account, t: &Value) -> Value {
    let mut v = t.clone();
    let transfer = number(t, "wallet_transfer_id");
    let delta = number(t, "delta");
    v["is_transfer"] = json!(transfer > 0);
    v["is_adjustment"] = json!(text(t, "type") == "adjustment");
    v["is_from_loan"] = json!(number(t, "loan_id") > 0);
    v["signed_amount"] = json!(format!(
        "{}{} ₫",
        if delta > 0 { "+" } else { "−" },
        money(delta.abs())
    ));
    v["display_color_class"] = json!(if delta > 0 {
        "text-green-600"
    } else {
        "text-red-600"
    });
    v["transacted_at_label"] = json!(
        date(text(t, "transacted_at"))
            .map(|d| d.format("%d/%m/%Y").to_string())
            .unwrap_or_default()
    );
    if transfer > 0 {
        let entries: Vec<_> = a
            .transactions
            .iter()
            .filter(|r| number(r, "wallet_transfer_id") == transfer)
            .collect();
        v["wallet_transfer"] = json!({"from_wallet_name":entries.iter().find(|r|number(r,"delta")<0).map(|r|r["wallet_name"].clone()),"to_wallet_name":entries.iter().find(|r|number(r,"delta")>0).map(|r|r["wallet_name"].clone())});
    }
    v
}
