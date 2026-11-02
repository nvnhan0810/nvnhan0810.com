//! Generate representative API payloads for the frontend contract smoke tests.
use serde_json::json;
use wallets::{
    application,
    domain::{finance, lending, *},
};
fn main() {
    let mut a = Account::default();
    a.normalize();
    finance::save_wallet(
        &mut a,
        None,
        &json!({"name":"Bank","type":"bank","balance":10000000}),
    )
    .unwrap();
    let loan=lending::create(&mut a,&json!({"type":"bank","name":"Mortgage","principal_amount":1200000,"started_at":today().to_string(),"term_months":12,"interest_rate":12,"monthly_payment":110000,"interest_calculation_method":"monthly","wallet_id":1,"payment_day":15})).unwrap();
    a.goal = json!({"name":"Debt goal","wallet_id":1,"loan_id":loan,"settlement_fees":[{"year":1,"type":"percent","value":2}]});
    finance::post(&mut a,&json!({"wallet_id":1,"type":"expense","amount":25,"description":"Groceries","transacted_at":today().to_string()})).unwrap();
    application::action(
        &mut a,
        "/recurring-items",
        "POST",
        &json!({"name":"Rent","type":"expense","amount":200,"day_of_month":15,"is_active":true}),
    )
    .unwrap();
    let paths = vec![
        "/".into(),
        "/wallets".into(),
        "/wallets/create".into(),
        "/wallets/1/edit".into(),
        "/transactions".into(),
        "/transactions/create".into(),
        "/transactions/bulk".into(),
        "/transaction-templates".into(),
        "/loans".into(),
        "/loans/create".into(),
        format!("/loans/{loan}"),
        format!("/loans/{loan}/edit"),
        "/recurring-items".into(),
        "/fixed-expenses".into(),
        "/settings".into(),
        "/holidays".into(),
        "/debt-goals".into(),
        "/debt-goals/detail".into(),
    ];
    let mut fixtures = paths
        .iter()
        .map(|path| application::pages::page(&a, path, &json!({})).unwrap())
        .collect::<Vec<_>>();
    fixtures.push(application::action(&mut a,"/loans/preview","POST",&json!({"type":"bank","name":"Preview","principal_amount":1000,"started_at":today().to_string(),"term_months":12,"interest_rate":12,"monthly_payment":100,"interest_calculation_method":"monthly"})).unwrap());
    println!("{}", serde_json::to_string_pretty(&fixtures).unwrap());
}
