pub mod finance;
pub mod legacy;
pub mod lending;
pub mod reports;
use chrono::{Datelike, NaiveDate, Utc};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};

pub type Result<T> = std::result::Result<T, String>;
#[derive(Clone, Serialize, Deserialize, Default)]
#[serde(default)]
pub struct Account {
    #[serde(skip)]
    pub original_holidays: Vec<Value>,
    pub next_id: i64,
    pub wallets: Vec<Value>,
    pub transactions: Vec<Value>,
    pub templates: Vec<Value>,
    pub loans: Vec<Value>,
    pub recurring: Vec<Value>,
    pub holidays: Vec<Value>,
    pub settings: Value,
    pub goal: Value,
    pub reminders: Vec<Value>,
}
impl Account {
    pub fn id(&mut self) -> i64 {
        self.next_id += 1;
        self.next_id
    }
    pub fn wallet(&self, id: i64) -> Result<&Value> {
        self.wallets
            .iter()
            .find(|v| number(v, "id") == id)
            .ok_or("Không tìm thấy ví".into())
    }
    pub fn wallet_mut(&mut self, id: i64) -> Result<&mut Value> {
        self.wallets
            .iter_mut()
            .find(|v| number(v, "id") == id)
            .ok_or("Không tìm thấy ví".into())
    }
    pub fn normalize(&mut self) {
        if !self.settings.is_object() {
            self.settings =
                json!({"recurring_alert_days":7,"telegram_enabled":false,"telegram_chat_id":""});
        }
    }
}
pub fn text<'a>(v: &'a Value, k: &str) -> &'a str {
    v[k].as_str().unwrap_or("")
}
pub fn number(v: &Value, k: &str) -> i64 {
    v[k].as_i64()
        .or_else(|| v[k].as_str().and_then(|s| s.parse().ok()))
        .unwrap_or(0)
}
pub fn flag(v: &Value, k: &str) -> bool {
    v[k].as_bool().unwrap_or(false)
}
pub fn required(v: &Value, k: &str) -> Result<String> {
    let s = text(v, k).trim();
    if s.is_empty() || s.len() > 1000 {
        Err(format!("{k}: giá trị không hợp lệ"))
    } else {
        Ok(s.into())
    }
}
/// Exact base-10 cents, never use binary floating point for ledger arithmetic.
pub fn cents(v: &Value, k: &str) -> Result<i64> {
    let raw = match &v[k] {
        Value::Number(n) => n.to_string(),
        Value::String(s) => s.clone(),
        Value::Null => "0".into(),
        _ => return Err(format!("{k}: số tiền không hợp lệ")),
    };
    let raw = raw.trim();
    let negative = raw.starts_with('-');
    let raw = raw.strip_prefix('-').unwrap_or(raw);
    let mut p = raw.split('.');
    let whole_raw = p.next().unwrap_or("");
    if whole_raw.is_empty() || !whole_raw.bytes().all(|b| b.is_ascii_digit()) {
        return Err(format!("{k}: số tiền không hợp lệ"));
    }
    let whole = whole_raw
        .parse::<i64>()
        .map_err(|_| format!("{k}: số tiền không hợp lệ"))?;
    let fraction = p.next().unwrap_or("");
    if p.next().is_some()
        || fraction.len() > 2
        || !fraction.bytes().all(|b| b.is_ascii_digit())
        || whole > 1_000_000_000_000
    {
        return Err(format!("{k}: tối đa 2 chữ số thập phân"));
    }
    let part = if fraction.is_empty() {
        0
    } else {
        fraction.parse::<i64>().unwrap() * if fraction.len() == 1 { 10 } else { 1 }
    };
    Ok((whole * 100 + part) * if negative { -1 } else { 1 })
}
pub fn money(c: i64) -> Value {
    json!(c as f64 / 100.0)
}
pub fn positive(v: &Value, k: &str) -> Result<i64> {
    let n = cents(v, k)?;
    if n <= 0 {
        Err(format!("{k}: phải lớn hơn 0"))
    } else {
        Ok(n)
    }
}
pub fn date(s: &str) -> Result<NaiveDate> {
    NaiveDate::parse_from_str(s, "%Y-%m-%d")
        .or_else(|_| NaiveDate::parse_from_str(s, "%d/%m/%Y"))
        .map_err(|_| "Ngày không hợp lệ".into())
}
pub fn today() -> NaiveDate {
    (Utc::now() + chrono::Duration::hours(7)).date_naive()
}
pub fn month_day(year: i32, month: u32, day: u32) -> NaiveDate {
    let next = if month == 12 {
        NaiveDate::from_ymd_opt(year + 1, 1, 1)
    } else {
        NaiveDate::from_ymd_opt(year, month + 1, 1)
    }
    .unwrap();
    NaiveDate::from_ymd_opt(
        year,
        month,
        day.clamp(1, (next - chrono::Duration::days(1)).day()),
    )
    .unwrap()
}
pub fn add_months(d: NaiveDate, n: u32, day: u32) -> NaiveDate {
    let total = d.year() * 12 + d.month0() as i32 + n as i32;
    month_day(total / 12, (total % 12 + 1) as u32, day)
}
