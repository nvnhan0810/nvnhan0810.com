use serde_json::{Value, json};
use wallets::{
    application,
    domain::{finance, lending, reports, *},
};
fn account() -> Account {
    let mut a = Account::default();
    a.normalize();
    for (name, balance) in [("Bank", 1000), ("Cash", 100)] {
        finance::save_wallet(
            &mut a,
            None,
            &json!({"name":name,"type":"bank","balance":balance}),
        )
        .unwrap();
    }
    a
}
fn post(a: &mut Account, kind: &str, amount: Value) -> i64 {
    finance::post(a,&json!({"type":kind,"amount":amount,"wallet_id":1,"description":"Test","transacted_at":today().to_string()})).unwrap()
}
fn loan() -> Value {
    json!({"name":"Loan","type":"bank","principal_amount":1200000,"interest_rate":12,"term_months":12,"started_at":today().to_string(),"payment_day":today().day(),"interest_calculation_method":"monthly","monthly_payment":110000,"record_cash_flow":false})
}
use chrono::Datelike;
#[test]
fn exact_decimal_ledger_and_reversal() {
    let mut a = account();
    let id = post(&mut a, "expense", json!("0.29"));
    assert_eq!(cents(a.wallet(1).unwrap(), "balance").unwrap(), 99971);
    finance::reverse(&mut a, id).unwrap();
    assert_eq!(cents(a.wallet(1).unwrap(), "balance").unwrap(), 100000);
    assert!(cents(&json!({"a":"0.001"}), "a").is_err());
}
#[test]
fn malformed_money_signs_are_rejected_without_panicking() {
    for amount in ["--9223372036854775808", "--1", "+1", "-+1", "1e3", ".5"] {
        assert!(
            cents(&json!({"amount":amount}), "amount").is_err(),
            "{amount}"
        );
    }
    assert_eq!(cents(&json!({"amount":"-0.29"}), "amount").unwrap(), -29);
}

#[test]
fn daily_minimum_first_period_moves_forward_to_a_working_day() {
    let mut input = loan();
    input["started_at"] = json!("2027-01-01");
    input["interest_calculation_method"] = json!("daily");
    input["payment_day"] = json!(1);
    let rows = lending::schedule(&input, &[json!({"date":"2027-02-03"})]).unwrap();
    assert_eq!(rows[0]["due_date"], "2027-02-04");
}

#[test]
fn daily_schedule_skips_holiday_runs_longer_than_ten_days() {
    let mut input = loan();
    input["started_at"] = json!("2027-01-01");
    input["interest_calculation_method"] = json!("daily");
    input["payment_day"] = json!(10);
    let holidays: Vec<_> = (10..=25)
        .map(|day| json!({"date":format!("2027-02-{day:02}")}))
        .collect();
    let rows = lending::schedule(&input, &holidays).unwrap();
    assert_eq!(rows[0]["due_date"], "2027-02-26");
}
#[test]
fn transfer_reverses_both_sides_and_fee() {
    let mut a = account();
    let id=finance::post(&mut a,&json!({"type":"transfer","from_wallet_id":1,"to_wallet_id":2,"amount":200,"fee":5,"description":"Transfer","transacted_at":today().to_string()})).unwrap();
    assert_eq!(a.wallet(1).unwrap()["balance"], 795.0);
    assert_eq!(a.wallet(2).unwrap()["balance"], 300.0);
    finance::reverse(&mut a, id).unwrap();
    assert!(a.transactions.is_empty());
    assert_eq!(a.wallet(1).unwrap()["balance"], 1000.0);
    assert_eq!(a.wallet(2).unwrap()["balance"], 100.0);
}
#[test]
fn credit_overpayment_reversal_restores_original_debt() {
    let mut a = account();
    finance::save_wallet(&mut a,None,&json!({"name":"Card","type":"credit_card","credit_limit":1000,"outstanding_balance":100,"statement_day":1,"payment_day":15})).unwrap();
    let id=finance::post(&mut a,&json!({"type":"income","wallet_id":3,"amount":200,"description":"Pay card","transacted_at":today().to_string()})).unwrap();
    finance::reverse(&mut a, id).unwrap();
    assert_eq!(a.wallet(3).unwrap()["outstanding_balance"], 100.0);
}
#[test]
fn transfer_cannot_reference_another_accounts_wallet() {
    let mut a = account();
    assert!(finance::post(&mut a,&json!({"type":"transfer","from_wallet_id":1,"to_wallet_id":999,"amount":200,"description":"Bad","transacted_at":today().to_string()})).is_err());
    assert_eq!(a.wallet(1).unwrap()["balance"], 1000.0);
}
#[test]
fn cashflow_excludes_internal_transfers_and_adjustments() {
    let mut a = account();
    post(&mut a, "income", json!(50));
    post(&mut a, "expense", json!(20));
    finance::post(&mut a,&json!({"type":"transfer","from_wallet_id":1,"to_wallet_id":2,"amount":200,"description":"Internal","transacted_at":today().to_string()})).unwrap();
    finance::post(&mut a,&json!({"type":"adjustment","wallet_id":1,"target_balance":900,"description":"Adjust","transacted_at":today().to_string()})).unwrap();
    for (period, count) in [("day", 14), ("week", 8), ("month", 6), ("year", 5)] {
        let r = reports::cashflow(&a, period);
        assert_eq!(r["current"]["income"], 50.0);
        assert_eq!(r["current"]["expense"], 20.0);
        assert_eq!(r["series"].as_array().unwrap().len(), count);
    }
}
#[test]
fn schedule_preserves_principal_and_exact_row_totals() {
    for method in ["monthly", "daily", "homecredit"] {
        let mut input = loan();
        input["interest_calculation_method"] = json!(method);
        input["collection_fee"] = json!(1000);
        let rows = lending::schedule(&input, &[]).unwrap();
        assert_eq!(rows.len(), 12);
        assert_eq!(
            rows.iter()
                .map(|r| cents(r, "principal").unwrap())
                .sum::<i64>(),
            120000000
        );
        for row in &rows {
            assert_eq!(
                cents(row, "payment").unwrap(),
                cents(row, "principal").unwrap()
                    + cents(row, "interest").unwrap()
                    + cents(row, "fee").unwrap()
            );
        }
        assert_eq!(rows.last().unwrap()["remaining_principal"], 0.0);
    }
}
#[test]
fn daily_first_period_is_at_least_33_days_and_skips_holidays() {
    let mut input = loan();
    input["started_at"] = json!("2027-01-01");
    input["interest_calculation_method"] = json!("daily");
    input["payment_day"] = json!(10);
    let rows = lending::schedule(&input, &[json!({"date":"2027-02-10"})]).unwrap();
    assert_eq!(rows[0]["due_date"], "2027-02-11");
    assert!(number(&rows[0], "days") >= 33);
}
#[test]
fn custom_schedule_must_balance_and_fully_repay() {
    let mut input = loan();
    input["term_months"] = json!(1);
    input["interest_calculation_method"] = json!("custom");
    input["custom_schedule"] =
        json!([{"due_date":"2099-01-01","principal":1,"interest":0,"fee":0,"payment":1}]);
    assert!(lending::schedule(&input, &[]).is_err());
    input["custom_schedule"][0]["principal"] = json!(1200000);
    input["custom_schedule"][0]["payment"] = json!(1200000);
    assert!(lending::schedule(&input, &[]).is_ok());
}
#[test]
fn repeated_period_payment_cannot_debit_twice() {
    let mut a = account();
    let id = lending::create(&mut a, &loan()).unwrap();
    let period = a.loans[0]["schedule"][0]["id"].clone();
    let data = json!({"loan_id":id,"wallet_id":1,"period_id":period,"amount":110000,"paid_at":today().to_string()});
    lending::payment(&mut a, &data).unwrap();
    let balance = a.wallet(1).unwrap()["balance"].clone();
    assert!(lending::payment(&mut a, &data).is_err());
    assert_eq!(a.wallet(1).unwrap()["balance"], balance);
}
#[test]
fn settlement_records_history_and_is_final() {
    let mut a = account();
    let id = lending::create(&mut a, &loan()).unwrap();
    lending::settle(
        &mut a,
        id,
        &json!({"wallet_id":1,"amount":500,"paid_at":today().to_string()}),
    )
    .unwrap();
    assert_eq!(a.loans[0]["payments"][0]["kind"], "settlement");
    assert_eq!(lending::remaining(&a.loans[0]), 0);
    assert!(lending::settle(&mut a, id, &json!({"wallet_id":1,"amount":500})).is_err());
}
#[test]
fn recurring_occurrences_are_unique_and_reversal_reopens() {
    let mut a = account();
    application::action(&mut a,"/recurring-items","POST",&json!({"name":"Rent","type":"expense","amount":200,"day_of_month":today().day(),"is_active":true})).unwrap();
    lending::generate_recurring(&mut a).unwrap();
    let count = a.reminders.len();
    lending::generate_recurring(&mut a).unwrap();
    assert_eq!(a.reminders.len(), count);
    let r = a.reminders[0].clone();
    let id=finance::post(&mut a,&json!({"type":"expense","amount":200,"wallet_id":1,"description":"Rent","transacted_at":today().to_string(),"recurring_item_id":r["recurring_item_id"],"recurring_occurrence_id":r["id"]})).unwrap();
    assert_eq!(a.reminders[0]["status"], "posted");
    finance::reverse(&mut a, id).unwrap();
    assert_eq!(a.reminders[0]["status"], "pending");
}
#[test]
fn every_existing_page_has_a_json_contract() {
    let a = account();
    for path in [
        "/",
        "/wallets",
        "/wallets/create",
        "/wallets/1/edit",
        "/wallets/sort",
        "/transactions",
        "/transactions/create",
        "/transactions/bulk",
        "/transaction-templates",
        "/loans",
        "/loans/create",
        "/recurring-items",
        "/fixed-expenses",
        "/settings",
        "/holidays",
        "/debt-goals",
    ] {
        let result = application::pages::page(&a, path, &json!({})).unwrap();
        assert!(result["component"].is_string(), "{path}");
        assert!(result["props"].is_object(), "{path}");
    }
}
#[test]
fn forged_loan_associations_rejected_by_public_actions() {
    let mut a = account();
    assert!(application::action(&mut a,"/transactions","POST",&json!({"loan_id":999,"type":"income","amount":5,"wallet_id":1,"description":"Bad","transacted_at":today().to_string()})).is_err());
}

#[test]
fn adjustment_can_be_saved_as_template_and_pagination_keeps_filters() {
    let mut a = account();
    finance::post(&mut a,&json!({"type":"adjustment","wallet_id":1,"target_balance":1200,"description":"Reconcile","transacted_at":today().to_string(),"save_as_template":true,"template_name":"Correction"})).unwrap();
    assert_eq!(a.templates[0]["amount"], 200.0);
    for _ in 0..35 {
        post(&mut a, "expense", json!(1));
    }
    let page = application::pages::page(
        &a,
        "/transactions",
        &json!({"wallet_id":"1","type":"expense","page":"1"}),
    )
    .unwrap();
    let url = page["props"]["transactions"]["links"][1]["url"]
        .as_str()
        .unwrap();
    assert!(url.contains("wallet_id=1"));
    assert!(url.contains("type=expense"));
    assert!(url.contains("page=2"));
}
