use crate::domain::{finance, lending, reports, *};
use serde_json::{Value, json};
fn paginated(items: Vec<Value>, page: i64, path: &str) -> Value {
    let total = items.len();
    let last = total.div_ceil(30).max(1);
    let page = page.max(1).min(last as i64);
    let links=(1..=last).map(|p|json!({"url":format!("{path}{}page={p}",if path.contains('?'){"&"}else{"?"}),"label":p.to_string(),"active":p as i64==page})).collect::<Vec<_>>();
    json!({"data":items.into_iter().skip((page as usize-1)*30).take(30).collect::<Vec<_>>(),"links":links,"meta":{"current_page":page,"last_page":last,"total":total}})
}
fn pagination_path(path: &str, q: &Value) -> String {
    let mut query = url::form_urlencoded::Serializer::new(String::new());
    for (key, value) in q.as_object().into_iter().flatten() {
        if key != "page" {
            query.append_pair(key, value.as_str().unwrap_or(""));
        }
    }
    let query = query.finish();
    if query.is_empty() {
        path.to_owned()
    } else {
        format!("{path}?{query}")
    }
}
fn transactions(a: &Account) -> Vec<Value> {
    let mut rows = a
        .transactions
        .iter()
        .map(|t| finance::transaction_view(a, t))
        .collect::<Vec<_>>();
    rows.sort_by(|a, b| {
        text(b, "transacted_at")
            .cmp(text(a, "transacted_at"))
            .then_with(|| number(b, "id").cmp(&number(a, "id")))
    });
    rows
}
pub fn page(a: &Account, path: &str, q: &Value) -> Result<Value> {
    let parts: Vec<_> = path.trim_matches('/').split('/').collect();
    let id = parts.get(1).and_then(|s| s.parse::<i64>().ok());
    let wallets: Vec<_> = a
        .wallets
        .iter()
        .filter(|w| flag(w, "is_active"))
        .map(finance::wallet_view)
        .collect();
    let mut loans = a.loans.clone();
    for loan in &mut loans {
        let remain = lending::remaining(loan);
        loan["remaining_principal"] = money(remain);
        loan["remaining_amount"] = money(remain);
        loan["payoff_remaining"] = money(remain);
        loan["remaining_interest"] = money(
            loan["schedule"]
                .as_array()
                .into_iter()
                .flatten()
                .filter(|r| !flag(r, "is_paid"))
                .map(|r| cents(r, "interest").unwrap_or(0))
                .sum(),
        );
        loan["months_passed"] = json!(
            loan["schedule"]
                .as_array()
                .into_iter()
                .flatten()
                .filter(|r| flag(r, "is_paid"))
                .count()
        );
        loan["remaining_months"] =
            json!((number(loan, "term_months") - number(loan, "months_passed")).max(0));
        loan["wallet"] = a
            .wallet(number(loan, "wallet_id"))
            .ok()
            .cloned()
            .unwrap_or(Value::Null);
    }
    let (component, props) = match parts[0] {
        "" => {
            let reminders = reports::reminders(a)?;
            let period = if text(q, "period").is_empty() {
                "month"
            } else {
                text(q, "period")
            };
            (
                "Dashboard/Index",
                json!({"wallets":wallets.iter().filter(|w|flag(w,"is_pinned")).collect::<Vec<_>>(),"totalDebt":money(loans.iter().filter(|l|text(l,"type")!="lend").map(lending::remaining).sum::<i64>()+a.wallets.iter().filter(|w|text(w,"type")=="credit_card"&&flag(w,"is_active")).map(|w|cents(w,"outstanding_balance").unwrap_or(0)).sum::<i64>()),"recentTransactions":transactions(a).into_iter().take(8).collect::<Vec<_>>(),"upcomingReminders":reminders,"cashFlowStats":reports::cashflow(a,period),"chartPeriod":period,"alertDays":a.settings["recurring_alert_days"],"walletsCount":a.wallets.len(),"unsettledLoansCount":loans.iter().filter(|l|!flag(l,"is_settled")).count()}),
            )
        }
        "wallets" => {
            if parts.get(1) == Some(&"create") || parts.get(2) == Some(&"edit") {
                (
                    "Wallets/Form",
                    json!({"wallet":if let Some(id)=id{finance::wallet_view(a.wallet(id)?)}else{Value::Null},"walletTypes":finance::wallet_types()}),
                )
            } else {
                let mut all = a
                    .wallets
                    .iter()
                    .map(|w| {
                        let mut w = finance::wallet_view(w);
                        w["transactions_count"] = json!(
                            a.transactions
                                .iter()
                                .filter(|t| t["wallet_id"] == w["id"])
                                .count()
                        );
                        w
                    })
                    .collect::<Vec<_>>();
                all.sort_by_key(|w| (!flag(w, "is_pinned"), number(w, "order")));
                (
                    "Wallets/Index",
                    json!({"wallets":all,"walletTypes":finance::wallet_types()}),
                )
            }
        }
        "transactions" => {
            if parts.get(1) == Some(&"create") {
                let balances = wallets
                    .iter()
                    .map(|w| (number(w, "id").to_string(), w["balance"].clone()))
                    .collect::<serde_json::Map<_, _>>();
                (
                    "Transactions/Create",
                    json!({"wallets":wallets,"templates":a.templates,"selectedTemplateId":number(q,"template_id"),"prefill":q,"walletBalances":balances}),
                )
            } else if parts.get(1) == Some(&"bulk") {
                (
                    "Transactions/Bulk",
                    json!({"wallets":wallets,"templates":a.templates,"defaultDate":today().format("%d/%m/%Y").to_string(),"defaultWalletId":if number(q,"wallet_id")>0{json!(number(q,"wallet_id"))}else{Value::Null}}),
                )
            } else {
                let id = number(q, "wallet_id");
                let list = transactions(a)
                    .into_iter()
                    .filter(|t| {
                        (id == 0 || number(t, "wallet_id") == id)
                            && (text(q, "type").is_empty() || text(t, "type") == text(q, "type"))
                    })
                    .collect();
                (
                    "Transactions/Index",
                    json!({"transactions":paginated(list,number(q,"page"),&pagination_path("/transactions",q)),"wallets":wallets,"filters":q}),
                )
            }
        }
        "transaction-templates" => {
            let mut templates = a.templates.clone();
            for t in &mut templates {
                for (field, key) in [
                    ("default_wallet_id", "default_wallet_name"),
                    ("from_wallet_id", "from_wallet_name"),
                    ("to_wallet_id", "to_wallet_name"),
                ] {
                    t[key] = a
                        .wallet(number(t, field))
                        .map(|w| w["name"].clone())
                        .unwrap_or(Value::Null);
                }
            }
            (
                "TransactionTemplates/Index",
                json!({"templates":templates,"wallets":wallets}),
            )
        }
        "loans" => {
            if parts.get(1) == Some(&"create") {
                (
                    "Loans/Create",
                    json!({"wallets":wallets,"draft":Value::Null}),
                )
            } else if let Some(id) = id {
                let loan = loans
                    .iter()
                    .find(|l| number(l, "id") == id)
                    .ok_or("Không tìm thấy khoản vay")?;
                if parts.get(2) == Some(&"edit") {
                    ("Loans/Edit", json!({"loan":loan,"wallets":wallets}))
                } else {
                    let mut loan = loan.clone();
                    loan["started_at"] = json!(
                        date(text(&loan, "started_at"))?
                            .format("%d/%m/%Y")
                            .to_string()
                    );
                    let timeline=loan["schedule"].as_array().into_iter().flatten().map(|r|json!({"type":"period","period":r,"period_due_date":r["due_date"],"is_paid":r["is_paid"],"period_payment":r["payment"],"payment":loan["payments"].as_array().and_then(|p|p.iter().find(|p|p["period_id"]==r["id"])),"note":""})).collect::<Vec<_>>();
                    (
                        "Loans/Show",
                        json!({"loan":loan,"schedule":loan["schedule"],"timeline":timeline,"monthsPassed":loan["schedule"].as_array().into_iter().flatten().filter(|r|flag(r,"is_paid")).count(),"paymentDay":loan["payment_day"],"wallets":wallets}),
                    )
                }
            } else {
                (
                    "Loans/Index",
                    json!({"loans":loans.iter().filter(|l|!flag(l,"is_settled")).collect::<Vec<_>>(),"wallets":wallets,"totalRemaining":money(loans.iter().filter(|l|text(l,"type")!="lend").map(lending::remaining).sum()),"totalLendRemaining":money(loans.iter().filter(|l|text(l,"type")=="lend").map(lending::remaining).sum())}),
                )
            }
        }
        "recurring-items" => {
            let items = a
                .recurring
                .iter()
                .map(|r| {
                    let mut r = r.clone();
                    let next = a
                        .reminders
                        .iter()
                        .filter(|v| {
                            v["recurring_item_id"] == r["id"] && text(v, "status") != "posted"
                        })
                        .min_by_key(|v| text(v, "due_date"));
                    r["next_due"] = next.map(|v| v["due_date"].clone()).unwrap_or(Value::Null);
                    r["days_until"] = next
                        .and_then(|v| date(text(v, "due_date")).ok())
                        .map(|d| json!((d - today()).num_days()))
                        .unwrap_or(Value::Null);
                    r["insufficient_funds"] = json!(false);
                    r
                })
                .collect::<Vec<_>>();
            ("RecurringItems/Index", json!({"items":items}))
        }
        "fixed-expenses" => ("FixedExpenses/Summary", reports::fixed(a, q)?),
        "settings" => (
            "Settings/Index",
            json!({"recurringAlertDays":a.settings["recurring_alert_days"],"telegramChatId":a.settings["telegram_chat_id"],"telegramEnabled":a.settings["telegram_enabled"]}),
        ),
        "holidays" => {
            let rows = a
                .holidays
                .iter()
                .map(|h| {
                    let mut h = h.clone();
                    h["date"] = json!(
                        date(text(&h, "date"))
                            .unwrap()
                            .format("%d/%m/%Y")
                            .to_string()
                    );
                    h
                })
                .collect();
            (
                "Holidays/Index",
                json!({"holidays":paginated(rows,number(q,"page"),"/holidays")}),
            )
        }
        "debt-goals" => {
            let progress = reports::progress(a);
            if parts.get(1) == Some(&"detail") {
                let loan = loans
                    .iter()
                    .find(|l| number(l, "id") == number(&a.goal, "loan_id"))
                    .ok_or("Mục tiêu chưa gắn khoản vay")?;
                let mut principal = cents(loan, "principal_amount")?;
                let mut rows = Vec::new();
                for r in loan["schedule"].as_array().into_iter().flatten() {
                    let index = number(r, "month_index");
                    let fee = reports::settlement_fee(&a.goal, (index + 11) / 12, principal);
                    let interest = cents(r, "interest")?;
                    let total = principal + fee + interest;
                    rows.push(json!({"month_index":index,"due_date":r["due_date"],"due_date_label":r["due_date_label"],"days":r["days"],"year":(index+11)/12,"principal_before":money(principal),"principal_remaining":r["remaining_principal"],"interest":r["interest"],"principal":r["principal"],"payment":r["payment"],"fee":r["fee"],"settlement_interest":money(interest),"settlement_fee":money(fee),"settlement_total":money(total),"is_paid":r["is_paid"],"is_current":index==loan["schedule"].as_array().into_iter().flatten().filter(|r|flag(r,"is_paid")).count() as i64+1,"progress_percent":if total>0{(cents(&progress,"collected")?as f64/total as f64*100.0).min(100.0)}else{0.0},"can_settle":cents(&progress,"collected")?>=total}));
                    principal = cents(r, "remaining_principal")?;
                }
                let current = rows
                    .iter()
                    .find(|r| flag(r, "is_current"))
                    .cloned()
                    .unwrap_or(Value::Null);
                (
                    "DebtGoals/Show",
                    json!({"goal_name":a.goal["name"],"loan":loan,"wallet":a.wallet(number(&a.goal,"wallet_id")).ok(),"months_passed":0,"payment_day":loan["payment_day"],"current":current,"collected":progress["collected"],"percent":progress["percent"],"rows":rows,"fees":a.goal["settlement_fees"]}),
                )
            } else {
                (
                    "DebtGoals/Index",
                    json!({"goal":if a.goal.is_object(){a.goal.clone()}else{json!({"name":"Mục tiêu trả nợ","wallet_id":null,"loan_id":null,"target_amount":null,"settlement_fees":[]})},"wallets":wallets.iter().filter(|w|text(w,"type")!="credit_card").collect::<Vec<_>>(),"loans":loans.iter().filter(|l|text(l,"type")=="bank"&&!flag(l,"is_settled")).collect::<Vec<_>>(),"fee_types":[{"value":"percent","label":"Phần trăm"},{"value":"fixed","label":"Cố định"}],"progress":progress}),
                )
            }
        }
        _ => return Err("Không tìm thấy trang".into()),
    };
    let mut props = props;
    props["debtGoal"] = reports::progress(a);
    Ok(json!({"component":component,"props":props}))
}
