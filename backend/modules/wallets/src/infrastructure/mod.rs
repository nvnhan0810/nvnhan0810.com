pub mod notifications;
use crate::domain::Account;
use sqlx::{PgPool, Postgres, Transaction};
use uuid::Uuid;
pub async fn load(tx: &mut Transaction<'_, Postgres>, owner: Uuid) -> Result<Account, sqlx::Error> {
    sqlx::query(
        "INSERT INTO wallets_accounts(user_id,payload) VALUES($1,$2) ON CONFLICT DO NOTHING",
    )
    .bind(owner)
    .bind(serde_json::to_value(Account::default()).unwrap())
    .execute(&mut **tx)
    .await?;
    let payload: serde_json::Value =
        sqlx::query_scalar("SELECT payload FROM wallets_accounts WHERE user_id=$1 FOR UPDATE")
            .bind(owner)
            .fetch_one(&mut **tx)
            .await?;
    serde_json::from_value(payload).map_err(|e| sqlx::Error::Decode(Box::new(e)))
}
pub async fn save(
    tx: &mut Transaction<'_, Postgres>,
    owner: Uuid,
    account: &Account,
) -> Result<(), sqlx::Error> {
    sqlx::query("UPDATE wallets_accounts SET payload=$2,updated_at=now() WHERE user_id=$1")
        .bind(owner)
        .bind(serde_json::to_value(account).unwrap())
        .execute(&mut **tx)
        .await?;
    Ok(())
}
pub async fn process(
    pool: &PgPool,
    auto_pay: bool,
) -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use crate::domain::*;
    let owners: Vec<Uuid> = sqlx::query_scalar("SELECT user_id FROM wallets_accounts")
        .fetch_all(pool)
        .await?;
    for owner in owners {
        let mut tx = pool.begin().await?;
        let mut a = load(&mut tx, owner).await?;
        a.normalize();
        lending::generate_recurring(&mut a)?;
        let loans = a.loans.clone();
        for l in &loans {
            if flag(l, "is_settled") {
                continue;
            }
            for r in l["schedule"].as_array().into_iter().flatten() {
                if flag(r, "is_paid") {
                    continue;
                }
                let due = date(text(r, "due_date"))?;
                if auto_pay && due <= today() && number(l, "wallet_id") > 0 {
                    crate::domain::lending::payment(
                        &mut a,
                        &serde_json::json!({"loan_id":l["id"],"wallet_id":l["wallet_id"],"period_id":r["id"],"amount":r["payment"],"paid_at":r["due_date"],"note":"Tự động thanh toán kỳ đến hạn"}),
                    )?;
                } else {
                    let loan = a.loans.iter_mut().find(|v| v["id"] == l["id"]).unwrap();
                    let row = loan["schedule"]
                        .as_array_mut()
                        .unwrap()
                        .iter_mut()
                        .find(|v| v["id"] == r["id"])
                        .unwrap();
                    row["status"] = serde_json::json!(if due < today() {
                        "overdue"
                    } else if due == today() {
                        "due"
                    } else {
                        "pending"
                    });
                }
            }
        }
        save(&mut tx, owner, &a).await?;
        tx.commit().await?;
    }
    Ok(())
}
