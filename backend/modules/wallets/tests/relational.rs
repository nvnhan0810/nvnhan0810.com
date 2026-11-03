use serde_json::{Value, json};
use sqlx::{Executor, postgres::PgPoolOptions};
use uuid::Uuid;
use wallets::{
    domain::{Account, finance, lending, number, today},
    infrastructure,
};

#[tokio::test]
#[ignore = "requires WALLETS_TEST_DATABASE_URL pointing to a disposable PostgreSQL database"]
async fn migrates_documents_to_typed_tables_and_preserves_finance_operations() {
    let pool = PgPoolOptions::new()
        .max_connections(1)
        .connect(&std::env::var("WALLETS_TEST_DATABASE_URL").unwrap())
        .await
        .unwrap();
    let mut tx = pool.begin().await.unwrap();
    tx.execute("CREATE SCHEMA finance_test").await.unwrap();
    tx.execute("SET LOCAL search_path TO finance_test")
        .await
        .unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../../identity/migrations/20261030000001_identity.up.sql"
    )))
    .await
    .unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261102000001_wallets.up.sql"
    )))
    .await
    .unwrap();
    let owner = Uuid::new_v4();
    let other = Uuid::new_v4();
    for (id, email) in [
        (owner, "nguyenvannhan0810@gmail.com"),
        (other, "other@example.com"),
    ] {
        sqlx::query(
            "INSERT INTO identity_users(id,google_subject,email,name) VALUES($1,$2,$3,'Test')",
        )
        .bind(id)
        .bind(id.to_string())
        .bind(email)
        .execute(&mut *tx)
        .await
        .unwrap();
    }
    let mut a = Account::default();
    a.normalize();
    for name in ["Bank", "Cash"] {
        finance::save_wallet(
            &mut a,
            None,
            &json!({"name":name,"type":"bank","balance":"1000000.29"}),
        )
        .unwrap();
    }
    let holiday_id = a.id();
    a.holidays
        .push(json!({"id":holiday_id,"date":"2027-01-01","name":"New Year","type":"public"}));
    finance::save_template(
        &mut a,
        &json!({"name":"Rent","type":"expense","amount":"100.01","default_wallet_id":1}),
    )
    .unwrap();
    lending::create(&mut a,&json!({"name":"Loan","type":"bank","principal_amount":"1200.00","interest_rate":0,"term_months":12,"started_at":"2026-01-01","months_paid":1,"payment_day":1,"interest_calculation_method":"monthly","monthly_payment":100,"wallet_id":1})).unwrap();
    let loan_id = a.loans[0]["id"].clone();
    let open = a.loans[0]["schedule"]
        .as_array()
        .unwrap()
        .iter()
        .find(|r| r["is_paid"] == false)
        .unwrap()["id"]
        .clone();
    lending::payment(&mut a,&json!({"loan_id":loan_id,"wallet_id":1,"period_id":open,"amount":100,"paid_at":today().to_string()})).unwrap();
    finance::post(&mut a,&json!({"type":"transfer","from_wallet_id":1,"to_wallet_id":2,"amount":"10.01","fee":"0.29","description":"Transfer","transacted_at":today().to_string()})).unwrap();
    a.goal = json!({"name":"Pay off","wallet_id":1,"loan_id":loan_id,"settlement_fees":[{"year":1,"type":"percent","value":2}]});
    let snapshot = serde_json::to_value(&a).unwrap();
    sqlx::query("INSERT INTO wallets_accounts(user_id,payload) VALUES($1,$2)")
        .bind(owner)
        .bind(&snapshot)
        .execute(&mut *tx)
        .await
        .unwrap();
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000001_relational_finance.up.sql"
    )))
    .await
    .unwrap();
    let doc: Option<String> =
        sqlx::query_scalar("SELECT to_regclass('finance_test.wallets_accounts')::text")
            .fetch_one(&mut *tx)
            .await
            .unwrap();
    assert!(doc.is_none());
    let column_type:String=sqlx::query_scalar("SELECT data_type FROM information_schema.columns WHERE table_schema='finance_test' AND table_name='wallets' AND column_name='balance'").fetch_one(&mut *tx).await.unwrap();
    assert_eq!(column_type, "numeric");
    let mut loaded = infrastructure::load(&mut tx, owner).await.unwrap();
    assert_eq!(loaded.wallets[0]["balance"], a.wallets[0]["balance"]);
    assert_eq!(loaded.transactions.len(), a.transactions.len());
    assert_eq!(
        loaded.loans[0]["payments"][0]["paid_at"],
        a.loans[0]["payments"][0]["paid_at"]
    );
    assert_eq!(
        loaded.goal["settlement_fees"][0]["value"].as_f64(),
        Some(2.0)
    );
    tx.execute("SET CONSTRAINTS ALL IMMEDIATE").await.unwrap();
    tx.execute("SAVEPOINT owner_boundary").await.unwrap();
    let forged = sqlx::query("INSERT INTO transaction_templates(user_id,id,name,type,amount,default_wallet_id) VALUES($1,999,'Invalid','expense',1,1)").bind(other).execute(&mut *tx).await;
    assert!(
        forged.is_err(),
        "Composite FK must reject another user's wallet"
    );
    tx.execute("ROLLBACK TO SAVEPOINT owner_boundary")
        .await
        .unwrap();
    tx.execute("SET CONSTRAINTS ALL DEFERRED").await.unwrap();
    let other_data = infrastructure::load(&mut tx, other).await.unwrap();
    assert!(other_data.wallets.is_empty());
    assert_eq!(other_data.holidays, loaded.holidays);
    // A shared holiday edit from another user survives an unrelated stale account save.
    let mut other_data = other_data;
    other_data
        .holidays
        .push(json!({"id":500,"date":"2027-02-01","name":"Shared","type":"bank"}));
    infrastructure::save(&mut tx, other, &other_data)
        .await
        .unwrap();
    let transfer_id = number(loaded.transactions.last().unwrap(), "id");
    finance::reverse(&mut loaded, transfer_id).unwrap();
    infrastructure::save(&mut tx, owner, &loaded).await.unwrap();
    tx.execute("SET CONSTRAINTS ALL IMMEDIATE").await.unwrap();
    tx.execute("SET CONSTRAINTS ALL DEFERRED").await.unwrap();
    let loaded = infrastructure::load(&mut tx, owner).await.unwrap();
    assert_eq!(loaded.holidays.len(), 2);
    assert_eq!(loaded.transactions.len(), 1);
    // Import legacy records to a second owner without creating ledger transactions.
    let mut source_periods = Vec::new();
    let mut source_payments = Vec::new();
    for loan in &a.loans {
        for row in loan["schedule"].as_array().unwrap() {
            let mut row = row.clone();
            row["loan_id"] = loan["id"].clone();
            source_periods.push(row);
        }
        for row in loan["payments"].as_array().unwrap() {
            let mut row = row.clone();
            row["loan_id"] = loan["id"].clone();
            row["schedule_month_index"] = row["month_index"].clone();
            source_payments.push(row);
        }
    }
    let raw = json!({"wallets":a.wallets,"transaction_templates":a.templates,"loans":a.loans,"loan_custom_schedules":source_periods,"payments":source_payments,"recurring_items":[],"recurring_occurrences":[],"holidays":a.holidays,"settings":[],"calculated_schedules":[]});
    let mut imported = wallets::domain::legacy::convert(&raw).unwrap();
    let previous = infrastructure::load(&mut tx, other).await.unwrap();
    imported.original_holidays = previous.holidays.clone();
    for h in previous.holidays {
        if !imported.holidays.iter().any(|r| r["date"] == h["date"]) {
            imported.holidays.push(h);
        }
    }
    infrastructure::save(&mut tx, other, &imported)
        .await
        .unwrap();
    tx.execute("SET CONSTRAINTS ALL IMMEDIATE").await.unwrap();
    tx.execute("SET CONSTRAINTS ALL DEFERRED").await.unwrap();
    let imported = infrastructure::load(&mut tx, other).await.unwrap();
    assert!(imported.transactions.is_empty());
    assert_eq!(imported.loans[0]["payments"].as_array().unwrap().len(), 1);
    assert_eq!(imported.holidays.len(), 2);
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000001_relational_finance.down.sql"
    )))
    .await
    .unwrap();
    let restored: Value =
        sqlx::query_scalar("SELECT payload FROM wallets_accounts WHERE user_id=$1")
            .bind(owner)
            .fetch_one(&mut *tx)
            .await
            .unwrap();
    assert_eq!(
        restored["loans"][0]["payments"].as_array().unwrap().len(),
        1
    );
    tx.execute(sqlx::raw_sql(include_str!(
        "../migrations/20261103000001_relational_finance.up.sql"
    )))
    .await
    .unwrap();
    tx.execute("SET CONSTRAINTS ALL IMMEDIATE").await.unwrap();
    let loaded = infrastructure::load(&mut tx, owner).await.unwrap();
    assert_eq!(loaded.wallets.len(), 2);
    assert_eq!(loaded.holidays.len(), 2);
    tx.rollback().await.unwrap();
    pool.close().await;
}
