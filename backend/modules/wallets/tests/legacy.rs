use serde_json::{Value, json};
use wallets::domain::{legacy, number};
fn snapshot() -> Value {
    json!({
    "wallets":[{"id":"10","user_id":999,"name":"Bank","type":"bank","balance":"987654.29","is_active":1,"is_pinned":0,"order":2}],
    "transaction_templates":[{"id":20,"name":"Rent","default_wallet_id":"10","type":"expense","amount":"123.45"}],
    "loans":[{"id":"30","user_id":1,"type":"bank","name":"Loan","wallet_id":"10","principal_amount":"1000.00","term_months":2,"months_paid":1,"started_at":"2026-01-01","is_settled":0}],
    "loan_custom_schedules":[{"id":40,"loan_id":"30","month_index":1,"due_date":"2026-02-01","payment":"500.00","remaining_principal":"500.00","status":"paid","payment_id":"50","reminded_telegram_at":"2026-02-01 12:00:00"},{"id":41,"loan_id":30,"month_index":2,"due_date":"2026-03-01","payment":"500.00","remaining_principal":"0.00","status":"pending"}],
    "payments":[{"id":50,"loan_id":30,"amount":"500.00","paid_at":"2026-02-01","transaction_id":9999,"schedule_month_index":1,"reduces_principal":1,"kind":"period"}],
    "recurring_items":[{"id":60,"name":"Rent","amount":"123.45","wallet_id":null,"type":"expense","is_active":1,"day_of_month":5}],
    "recurring_occurrences":[{"id":70,"recurring_item_id":"60","due_date":"2026-02-05","status":"posted","expected_amount":"123.45","transaction_id":111,"posted_amount":"125.00"}],
    "holidays":[{"id":80,"date":"2026-01-01","name":"New Year","type":"public"}],
    "settings":[{"id":90,"key":"telegram_enabled","value":"1"},{"id":91,"key":"debt_goal_name","value":"Pay off"},{"id":92,"key":"debt_goal_wallet_id","value":"10"},{"id":93,"key":"debt_goal_loan_id","value":"30"},{"id":94,"key":"debt_goal_settlement_fees","value":"[{\"year\":1,\"type\":\"percent\",\"value\":2}]"}],
    "calculated_schedules":[],
    "transactions":[{"id":9999}],"wallet_transfers":[{"id":1}]
    })
}
#[test]
fn imports_all_finance_entities_without_transactions_or_source_users() {
    let a = legacy::convert(&snapshot()).unwrap();
    assert!(a.transactions.is_empty());
    assert_eq!(a.wallets[0]["balance"], "987654.29");
    assert_eq!(a.wallets[0]["is_active"], true);
    assert_eq!(a.wallets[0]["is_pinned"], false);
    assert!(a.wallets[0].get("user_id").is_none());
    assert_eq!(a.loans[0]["schedule"][0]["is_paid"], true);
    assert_eq!(a.loans[0]["schedule"][1]["is_paid"], false);
    assert_eq!(a.loans[0]["payments"][0]["paid_at"], "01/02/2026");
    assert!(a.loans[0]["payments"][0].get("transaction_id").is_none());
    assert_eq!(a.reminders[0]["status"], "posted");
    assert!(a.reminders[0].get("transaction_id").is_none());
    assert_eq!(a.settings["telegram_enabled"], true);
    assert_eq!(a.goal["wallet_id"], 10);
    assert_eq!(a.goal["settlement_fees"][0]["value"], 2);
    assert!(a.next_id >= 80);
}
#[test]
fn rejects_missing_tables_and_broken_relationships() {
    let mut s = snapshot();
    s.as_object_mut().unwrap().remove("payments");
    assert!(legacy::convert(&s).is_err());
    let mut s = snapshot();
    s["loans"][0]["wallet_id"] = json!(999);
    assert!(legacy::convert(&s).is_err());
}
#[test]
fn allocates_new_ids_for_calculated_periods_above_imported_ids() {
    let mut s = snapshot();
    s["loan_custom_schedules"] = json!([]);
    s["calculated_schedules"] = json!([{"loan_id":30,"month_index":1,"due_date":"2026-02-01","remaining_principal":500,"payment":500}]);
    let a = legacy::convert(&s).unwrap();
    assert!(number(&a.loans[0]["schedule"][0], "id") > 80);
}
