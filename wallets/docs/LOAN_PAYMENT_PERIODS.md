# Kỳ thanh toán khoản vay & Cron

> **Cập nhật 07/2026 (tách ví ↔ khoản vay):** Xem chi tiết thiết kế ở `LOAN_WALLET_REDESIGN_PLAN.md`.
> - Kỳ trả được vật chất hóa vào `loan_custom_schedules` (thêm `due_date`, `status`, `payment_id`, `paid_amount`).
> - Tạo khoản vay: chỉ ghi giao dịch vào ví khi **ngày bắt đầu là hôm nay** + tick nhận tiền (có ô "số tiền thực nhận"). Ngày bắt đầu trong quá khứ → các kỳ tới hạn ≤ hôm nay tự đánh dấu **đã trả** (không sinh giao dịch).
> - Khi trả: chọn ví để trừ tiền, kỳ tương ứng chuyển **paid**.
> - Dashboard nhắc kỳ trong `recurring_alert_days` ngày (kèm kỳ **quá hạn**). Đúng ngày chưa trả → gửi **Telegram** kèm link mở modal ghi trả.
> - Lệnh: `php artisan loans:generate-periods [--loan=ID] [--no-backfill]` (sinh/điền kỳ cho khoản vay cũ), `php artisan loans:process-periods` (cập nhật due/overdue + gửi Telegram, chạy 07:00).
> - Cấu hình Telegram: `TELEGRAM_BOT_TOKEN` (env, dùng chung) + Chat ID theo user tại trang **Cài đặt**.


## Tính năng

### Thanh toán trước (early)
- Thanh toán **trước ngày kỳ cố định** (`payment_day`, VD: 25) → ghi nhận ví, **không trừ gốc**.
- Trên lịch khoản vay (`/loans/{id}`): hiện dòng xanh **TT trước** giữa hai kỳ, kèm ghi chú *Gốc còn lại (kỳ trước)*.
- Dashboard **không nhắc** chi cố định / khoản vay nếu đã thanh toán trước cho kỳ sắp tới.

### Từ 06/2026
- Chỉ thanh toán **đúng kỳ** (`paid_at` ≥ ngày đến hạn kỳ) mới:
  - Đánh dấu kỳ **Đã trả** trên lịch
  - Cập nhật `months_paid` / trừ gốc theo lịch amortization

Cấu hình: `LOAN_PRINCIPAL_REDUCTION_CUTOFF=2026-06-01` trong `.env` (mặc định trong `config/loans.php`).

### Chi cố định
- Tạo vay ngân hàng + tick **Tạo chi cố định** → tự tạo `recurring_items` gắn `loan_id`.
- Ngày chi = `payment_day` trên khoản vay.

## Cron (production)

Thêm vào crontab user chạy web (mỗi phút gọi scheduler Laravel):

```cron
* * * * * cd /opt/apps/debt.nvnhan0810.com && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler chạy lệnh (mỗi ngày 07:00):

```bash
php artisan finance:process-reminders
```

> Lệnh gộp: cập nhật trạng thái kỳ vay + kỳ thu/chi cố định và gửi Telegram nhắc.
> (Lệnh cũ `loans:sync-payment-periods` đã bị bỏ khi tách recurring khỏi khoản vay.)

Chạy tay:

```bash
cd /opt/apps/debt.nvnhan0810.com
php artisan migrate
php artisan view:clear
php artisan finance:process-reminders
```

## Migration

`2026_05_20_000001_loan_payment_periods.php`

- `loans.payment_day`
- `payments.kind`, `period_due_date`, `schedule_month_index`, `reduces_principal`

`2026_07_17_000020_decouple_recurring_from_loans.php` (tách recurring khỏi vay)

- Drop `loans.recurring_item_id`, `recurring_items.loan_id`

## Files đã chỉnh

| File | Thay đổi |
|------|----------|
| `config/loans.php` | Ngày bắt đầu bắt buộc TT để trừ gốc |
| `app/Services/LoanPaymentScheduleService.php` | Kỳ, TT trước, timeline, sync recurring |
| `app/Services/LoanPaymentReminderService.php` | Nhắc vay (theo kỳ) |
| `app/Console/Commands/ProcessRemindersCommand.php` | Cron gộp `finance:process-reminders` (vay + thu/chi) |
| `routes/console.php` | `Schedule::command('finance:process-reminders')` |
| `app/Http/Controllers/LoanController.php` | store/storePayment/show/index |
| `app/Http/Controllers/DashboardController.php` | Nhắc khoản vay |
| `app/Services/RecurringItemService.php` | Ẩn nhắc nếu đã TT trước |
| `app/Models/Loan.php`, `Payment.php`, `RecurringItem.php` | Fields & relations |
| `resources/views/loans/show.blade.php` | Timeline + lịch sử TT |
| `resources/views/loans/create.blade.php` | `payment_day`, link recurring |
| `resources/views/loans/index.blade.php` | Gợi ý TT trước |
| `resources/views/dashboard/index.blade.php` | Block nhắc khoản vay |
