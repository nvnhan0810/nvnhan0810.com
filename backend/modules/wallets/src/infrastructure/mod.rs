pub mod notifications;
mod relational;
mod schema;
pub use relational::{load, save};
use sqlx::PgPool;
use uuid::Uuid;
pub async fn process(
    pool: &PgPool,
    auto_pay: bool,
) -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use crate::domain::*;
    let owners: Vec<Uuid> = sqlx::query_scalar("SELECT user_id FROM finance_profiles")
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
