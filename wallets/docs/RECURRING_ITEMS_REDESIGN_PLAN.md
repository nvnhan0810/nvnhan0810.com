# Kế hoạch: Gỡ recurring khỏi khoản vay & Củng cố thu/chi cố định

> ✅ **ĐÃ TRIỂN KHAI** (Phase A + B1–B6 + tests). Auto-post: không làm. Backfill lịch sử: bỏ qua (chưa có data).
>
> Tóm tắt hiện thực:
> - Bảng `recurring_occurrences` (kỳ có trạng thái `pending|due|overdue|posted|skipped`), unique `(recurring_item_id, due_date)`.
> - `recurring_items.effective_from`/`ends_at`; `transactions.recurring_item_id`/`recurring_occurrence_id`.
> - `RecurringOccurrenceGenerator` (sinh kỳ, không backfill quá khứ) + `RecurringOccurrenceStateService` (transition/markPosted/nhắc).
> - Ghi giao dịch từ nhắc → prefill + gắn kỳ → `posted` (hết nhắc trùng, truy vết được).
> - Cron gộp `finance:process-reminders` (vay + thu/chi) 07:00; đã bỏ `loans:sync-payment-periods` và mọi liên kết loan↔recurring.

> Hai mục tiêu:
> 1. **Gỡ recurring cho khoản vay** — khoản vay đã có nhắc riêng theo kỳ + Telegram (`loans:process-periods`), không cần recurring item "Trả vay: …" nữa.
> 2. **Khắc phục 4 điểm yếu của thu/chi cố định**: (1) không theo dõi "đã ghi kỳ này chưa" → nhắc trùng / kỳ lỡ biến mất; (2) không truy vết ngược (transaction thiếu `recurring_item_id`); (3) không nhắc Telegram; (4) không có ngày bắt đầu/kết thúc.

---

## PHẦN A — Gỡ recurring khỏi khoản vay

### A0. Hiện trạng liên kết
- `LoanPaymentScheduleService::syncRecurringItem()` tạo/cập nhật `RecurringItem` "Trả vay: X" (type=expense), gọi từ `CreateLoanHandler` (khi `linkRecurring`) và `RecordLoanPaymentHandler`.
- Cột: `loans.recurring_item_id`, `recurring_items.loan_id`.
- Dashboard `GetDashboardOverviewHandler::buildUpcomingReminders` + `LoanPaymentReminderService::filterRecurringWithEarlyCoverage` dedup item gắn khoản vay.
- Form `loans/create.blade.php` có checkbox **link_recurring**.

### A1. Ngưng tạo recurring cho khoản vay
- Bỏ gọi `syncRecurringItem()` trong `CreateLoanHandler` và `RecordLoanPaymentHandler`.
- `LoanPaymentScheduleService::syncRecurringItem()`: giữ method (tránh vỡ tham chiếu) nhưng chuyển thành no-op **hoặc** xóa hẳn cùng các lời gọi. Đề xuất: xóa hẳn để sạch.
- Bỏ checkbox **link_recurring** khỏi form tạo + tham số `linkRecurring` ở `CreateLoan`/controller.

### A2. Dọn dữ liệu cũ
- **Không cần** — hiện chưa có dữ liệu recurring nào trong app, nên không phải viết lệnh dọn.

### A3. Dọn code dedup không còn cần
- Bỏ `filterRecurringWithEarlyCoverage` khỏi `RecurringItemService::upcoming` (và method này) — không còn recurring gắn loan.
- Đơn giản hóa `buildUpcomingReminders` (bỏ nhánh dedup `loan_id`).

### A4. Bỏ cột liên kết
- Vì chưa có dữ liệu, có thể drop thẳng `recurring_items.loan_id` và `loans.recurring_item_id` trong 1 migration (không cần bước dọn trước).

---

## PHẦN B — Củng cố thu/chi cố định

> Áp dụng đúng mô hình "kỳ có trạng thái" như khoản vay để đồng bộ toàn hệ thống: **vật chất hóa từng lần phát sinh (occurrence)**.

### B1. Dữ liệu (migrations)

**Bảng mới `recurring_occurrences`** — mỗi lần phát sinh hàng tháng = 1 dòng:

| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `id` | PK | |
| `recurring_item_id` | FK → recurring_items (cascade) | |
| `user_id` | FK → users | |
| `due_date` | date | ngày đến hạn của kỳ |
| `expected_amount` | decimal(15,2) | snapshot số tiền dự kiến |
| `status` | string default `pending` | `pending\|due\|overdue\|posted\|skipped` |
| `transaction_id` | FK → transactions (nullOnDelete), nullable | giao dịch thực tế |
| `posted_amount` | decimal(15,2) nullable | |
| `posted_at` | date nullable | |
| `reminded_telegram_at` | timestamp nullable | chống gửi trùng |
| `timestamps` | | |

Unique `(recurring_item_id, due_date)`; index `(user_id, status, due_date)`.

**ALTER `recurring_items`** (điểm yếu #4): thêm `effective_from` (date, nullable), `ends_at` (date, nullable).

**ALTER `transactions`** (điểm yếu #2): thêm `recurring_item_id` (nullable FK, nullOnDelete). (Có thể thêm cả `recurring_occurrence_id` để truy vết chính xác kỳ — đề xuất thêm để rõ ràng.)

### B2. Sinh occurrence — `RecurringOccurrenceGenerator`
`src/Wallets/RecurringPlanning/Application/RecurringOccurrenceGenerator.php`
- `ensure(RecurringItem $item, Carbon $horizon): void` — tạo các occurrence còn thiếu từ mốc bắt đầu tới `horizon`.
  - Mốc bắt đầu = `max(effective_from, đầu tháng hiện tại)` → **không backfill quá khứ** (không có data cũ nên bỏ qua chuyện lịch sử).
  - `horizon` = hôm nay + `recurring_alert_days` (đủ để nhắc trước).
  - Dừng khi vượt `ends_at`.
  - Idempotent nhờ unique `(recurring_item_id, due_date)`.
- Gọi khi: tạo/sửa item; và trong lệnh cron trước khi nhắc.

### B3. Trạng thái & ghi nhận — `RecurringOccurrenceStateService`
- `transitionStatuses(?int $userId=null)` — kỳ mở: `due_date < today` → `overdue`; `== today` → `due`; `> today` → `pending`.
- `markPosted(RecurringOccurrence $occ, Transaction $tx)` — set `posted`, gán `transaction_id/posted_amount/posted_at`.
- `dueTodayUnremindedOccurrences(int $userId)` / `upcomingOccurrences(int $userId, int $withinDays)` — cho Telegram / dashboard.

### B4. Luồng ghi giao dịch (điểm yếu #1, #2)
- Prefill "Ghi giao dịch" từ dashboard/recurring page mang thêm `recurring_item_id`, `recurring_occurrence_id`, `transacted_at` (= due_date), `category`.
- `TransactionController::create` nhận và đẩy các field này vào `prefill`; form `transactions/create.blade.php` render hidden `recurring_item_id`, `recurring_occurrence_id`.
- `RecordIncomeExpense` command + handler: nhận `recurring_item_id` (+ `recurring_occurrence_id`) → set trên `Transaction`. Sau khi tạo tx, nếu có occurrence_id → `RecurringOccurrenceStateService::markPosted()`.
- Kết quả: kỳ đã ghi → `posted` → **biến mất khỏi nhắc** (hết nhắc trùng); còn truy vết được tx ↔ item ↔ kỳ.

### B5. Nhắc dashboard theo occurrence (điểm yếu #1)
- `RecurringItemService::upcoming()` đổi nguồn sang `recurring_occurrences` (status mở, `due_date <= today + withinDays`, gồm cả **overdue**). Giữ `insufficient_funds` (tính từ ví + expected_amount).
- Dashboard hiển thị "Quá hạn N ngày" cho kỳ trễ; link "Ghi giao dịch" prefill như B4.

### B6. Telegram (điểm yếu #3) — cron GỘP
- Tận dụng `App\Services\TelegramNotifier` sẵn có.
- **Gộp** thành 1 lệnh chung `finance:process-reminders` (thay `loans:process-periods`), lo cả khoản vay + thu/chi:
  1. Khoản vay: `LoanScheduleStateService::transitionStatuses()` + gửi Telegram kỳ vay đến hạn (logic đang có trong `loans:process-periods`).
  2. Thu/chi: `RecurringOccurrenceGenerator::ensure` cho item active → `RecurringOccurrenceStateService::transitionStatuses()` → gửi Telegram occurrence `due_date <= today`, chưa `posted`, chưa nhắc hôm nay → set `reminded_telegram_at`.
- Nội dung: tên khoản, số tiền, ngày đến hạn, nút link "Ghi giao dịch" (deep-link tới `transactions.create` prefill).
- `routes/console.php`: thay `loans:process-periods` bằng `finance:process-reminders` `dailyAt('07:00')`. Giữ lệnh `loans:process-periods` cũ (không lịch) hoặc bỏ — đề xuất: cho `loans:process-periods` gọi lại logic chung để không trùng code.

> **Auto-post: KHÔNG làm.** Luôn ghi giao dịch thủ công (chọn ví khi ghi), giống khoản vay.

---

## Đăng ký & wiring
- `WalletsServiceProvider`: đăng ký command mới (nếu có `GenerateRecurringOccurrences`), cập nhật map `RecordIncomeExpense` (không đổi class, chỉ thêm field data). Bind các service mới (autowire được).
- `AmortizationCalculator`/loan không đổi.

## Kiểm thử (`tests/Feature/RecurringPlanning`)
- `GenerateOccurrenceTest`: item tạo hôm nay → sinh kỳ tháng này/tháng tới; không backfill quá khứ; tôn trọng `ends_at`.
- `RecordFromRecurringMarksPostedTest`: ghi giao dịch từ kỳ → tx có `recurring_item_id`, kỳ = `posted`, ví cập nhật, kỳ biến mất khỏi nhắc.
- `RecurringOverdueTest`: kỳ quá ngày chưa ghi → `overdue`, vẫn hiện nhắc.
- `RecurringTelegramTest`: kỳ đến hạn chưa ghi → gửi Telegram + set reminded (Http::fake).
- `LoanNoLongerCreatesRecurringTest`: tạo/trả khoản vay bank → KHÔNG tạo `recurring_items`.

## Thứ tự triển khai (phases)
1. **A1 + A3 + A4**: ngưng tạo recurring cho vay + dọn code dedup + drop cột `loan_id`/`recurring_item_id`.
2. **B1**: migrations (occurrences, ALTER recurring_items, ALTER transactions).
3. **B2–B3**: generator + state service (+ model `RecurringOccurrence`).
4. **B4**: luồng ghi giao dịch gắn kỳ (prefill + RecordIncomeExpense + markPosted).
5. **B5**: nhắc dashboard theo occurrence.
6. **B6**: Telegram + cron gộp `finance:process-reminders`.
7. Tests + cập nhật docs.

## Đã chốt
1. **Backfill lịch sử**: bỏ qua (chưa có data) — chỉ theo dõi từ hiện tại trở đi.
2. **Cron**: gộp 1 lệnh `finance:process-reminders` cho cả vay + thu/chi.
3. **Auto-post**: KHÔNG làm — luôn ghi giao dịch thủ công.
