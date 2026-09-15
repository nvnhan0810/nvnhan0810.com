# Kế hoạch: Tách khoản vay ↔ ví, kỳ trả có trạng thái, nhắc Telegram

> Mục tiêu: Khoản vay và ví **độc lập**. Chỉ tạo giao dịch ví khi (a) tạo khoản vay bắt đầu **hôm nay** và người dùng chọn nhận tiền, hoặc (b) khi **trả** một kỳ. Kỳ trả được lưu thành bản ghi có trạng thái để nhắc dashboard (1 tuần trước) và nhắc Telegram (đúng ngày, nếu chưa trả). Khoản vay tạo hồi tố (bắt đầu trong quá khứ) tự đánh dấu các kỳ cũ là đã xong.

## 0. Tổng quan luồng mong muốn

1. **Tạo khoản vay**
   - `started_at != hôm nay` → chỉ tạo bản ghi khoản vay + sinh lịch kỳ trả. **Không** tạo giao dịch ví.
   - `started_at == hôm nay` → hiện tùy chọn *"Nhận tiền vào ví"*: chọn ví + **số tiền thực nhận** (có thể ≠ gốc). Submit → tạo giao dịch thu vào ví.
2. **Kỳ trả (materialized)**
   - Trong vòng **7 ngày** trước ngày đến hạn → hiện nhắc trên **dashboard**.
   - **Đúng ngày đến hạn** mà chưa trả → gửi **Telegram** kèm link tạo khoản trả.
   - Khi **trả**: chọn ví để trừ tiền → tạo giao dịch chi tương ứng, đánh dấu kỳ *đã trả*.
3. **Khoản vay hồi tố**: các kỳ có ngày đến hạn ≤ hôm nay tại thời điểm tạo → tự đánh dấu *đã trả* (backfill, không sinh giao dịch ví).

## 1. Hiện trạng (điểm chạm)

- Kiến trúc DDD: Command/Query Bus đăng ký ở `app/Providers/WalletsServiceProvider.php`.
- Module: `src/Wallets/Lending/**`.
- Liên kết sẵn có: `Loan.wallet_id`, `Payment.transaction_id`, `Transaction.loan_id` + `loan_payment_id`.
- Lịch trả **chưa được vật chất hóa** — tính on-the-fly trong `AmortizationCalculator` + `LoanPaymentScheduleService`.
- Nhắc hiện dựa trên `recurring_items` (auto-tạo "Trả vay: …") + `LoanPaymentReminderService`. Hàm `nextPaymentDueDate()` tính từ `today` → **kỳ quá hạn bị nhảy sang tháng sau** (mất dấu).
- Chưa có tích hợp Telegram.
- Cron: `routes/console.php` → `loans:sync-payment-periods` chạy 06:00.

> **Ghi chú tái sử dụng bảng:** Toàn bộ khái niệm "kỳ trả / period" trong plan này ánh xạ tới bảng có sẵn **`loan_custom_schedules`** (model `LoanCustomSchedule`) sau khi được mở rộng cột — KHÔNG tạo bảng mới.

## 2. Thay đổi dữ liệu (migrations)

### 2.1. Tái sử dụng & tổng quát hóa bảng `loan_custom_schedules`
> Đã có bảng `loan_custom_schedules` (model `App\Models\LoanCustomSchedule`) lưu lịch kỳ. **Tái sử dụng** thay vì tạo bảng mới. Hiện tại nó chỉ được ghi khi `interest_calculation_method === 'custom'`; ta sẽ mở rộng để lưu kỳ cho **mọi** khoản vay và thêm trạng thái.

**Cột hiện có**: `loan_id, month_index, payment, principal, interest, fee, remaining_principal, paid_at, note`.

**Migration bổ sung cột (ALTER):**

| Cột mới | Kiểu | Ghi chú |
|--------|------|---------|
| `user_id` | FK → users, nullable | tiện query/nhắc theo user (backfill từ loan) |
| `due_date` | date, nullable | **ngày đến hạn (canonical)** |
| `status` | string, default `pending` | `pending\|due\|overdue\|paid\|skipped` |
| `payment_id` | FK → payments (nullOnDelete), nullable | lần trả thực tế |
| `paid_amount` | decimal(15,2), nullable | số đã trả thực |
| `reminded_telegram_at` | timestamp, nullable | chống gửi Telegram trùng |

Index thêm: `(user_id, status, due_date)`; unique `(loan_id, month_index)`.

**Sửa ngữ nghĩa `paid_at`** (đang bị overload = *ngày kế hoạch* trong chế độ custom):
- Trong migration: với rows custom cũ, set `due_date = paid_at` rồi **để `paid_at` về null** (vì chưa trả thực). Từ nay `paid_at` = *ngày trả thực tế*, `due_date` = *ngày đến hạn*.
- Sửa `AmortizationCalculator` (dòng ~34) đọc `due_date` (fallback `paid_at`) thay vì `paid_at` để dựng lịch custom.

**Điểm chạm khi tổng quát hóa** (nay ghi rows cho cả monthly/daily, không chỉ custom):
- `CreateLoanHandler`: chuyển phần tạo `LoanCustomSchedule` (custom) vào chung `LoanScheduleGenerator`.
- `AmortizationCalculator::calculate` (nhánh không-custom) vẫn tính như cũ để hiển thị/tham chiếu; `LoanScheduleGenerator` gọi nó rồi **persist** kết quả vào `loan_custom_schedules`.
- Tên bảng giữ nguyên để giảm rủi ro (có thể rename `loan_schedules` sau — không bắt buộc). Model có thể thêm alias/relations `periods()`.

### 2.2. Cấu hình Telegram theo user
- `users`: thêm `telegram_chat_id` (string, nullable) — hoặc lưu qua `Setting` key `telegram_chat_id`. **Chọn `Setting`** để đồng bộ pattern hiện có (không đụng bảng users). Thêm key: `telegram_chat_id`, `telegram_enabled`.
- `config/services.php`: thêm `telegram.bot_token` = `env('TELEGRAM_BOT_TOKEN')` (token bot dùng chung toàn hệ thống).
- `.env.example`: thêm `TELEGRAM_BOT_TOKEN=`.

### 2.3. Backfill khi migrate
- Migration `up()` ALTER bảng, sau đó **sinh/điền kỳ cho các khoản vay hiện có** (`type = bank`, chưa tất toán) từ amortization; kỳ `due_date <= today` → `status = paid` (backfill). Rows custom cũ: chỉ cần set `due_date`, `status` theo mốc hôm nay. (Có thể tách sang lệnh artisan `loans:generate-periods --backfill` để chạy tay an toàn hơn — xem 4.4.)

## 3. Domain / Application (module Lending)

### 3.1. Model tái sử dụng `LoanCustomSchedule`
- `app/Models/LoanCustomSchedule.php`: bổ sung `fillable`/`casts` cho `user_id, due_date, status, payment_id, paid_amount, reminded_telegram_at`; dùng trait `BelongsToUser`; thêm quan hệ `belongsTo(Payment)`, `belongsTo(Loan)`; hằng số status + helper `isOverdue()`, `markPaid()`. (Cân nhắc alias tên khái niệm "period" trong code.)
- `Loan`: đã có `customSchedules()` (`hasMany(LoanCustomSchedule)`) — thêm alias `periods()` trỏ cùng quan hệ để đọc rõ nghĩa. Số thực nhận **không** cần cột trên loan (chỉ ảnh hưởng giao dịch ví); nếu muốn đối chiếu, thêm cột tùy chọn `disbursed_amount` (mặc định bỏ qua).

### 3.2. Service mới `LoanScheduleGenerator`
`src/Wallets/Lending/Application/LoanScheduleGenerator.php`
- `generate(Loan $loan, bool $backfillPast = true): void` — ghi vào **`loan_custom_schedules`**.
  - Vay ngân hàng (monthly/daily): dùng `AmortizationCalculator::calculate(...)` → persist mỗi dòng (due_date = `LoanPaymentScheduleService::periodDueDate`, map `payment/principal/interest/fee/remaining_principal`).
  - Custom: gộp phần tạo rows đang nằm ở `CreateLoanHandler` vào đây, kèm set `due_date`.
  - borrow/lend: nếu có `term_months` + `monthly_payment` → chia đều; nếu không → tạo 1 kỳ đến hạn = ngày thỏa thuận (hoặc bỏ qua, chỉ nhắc thủ công). *(Chốt ở mục Quyết định.)*
  - `backfillPast`: kỳ có `due_date <= today` → `status = paid`, set `paid_at = due_date`, `paid_amount = payment` (không tạo `Payment`/giao dịch ví). Cập nhật `loan.months_paid`.
- Idempotent: nếu đã có kỳ thì chỉ tạo kỳ thiếu / cập nhật an toàn.

### 3.3. Service mới `LoanScheduleStateService`
`src/Wallets/Lending/Application/LoanScheduleStateService.php`
- `transitionStatuses(?int $userId = null): void` — cho kỳ chưa trả: `due_date == today` → `due`; `due_date < today` → `overdue`; tương lai → `pending`.
- `linkPaymentToPeriod(Payment $payment, ?int $periodId = null): void` — gán payment vào kỳ (mặc định: kỳ `overdue`/`due`/`pending` cũ nhất chưa trả), set `status = paid`, `paid_amount`, `paid_at`, `payment_id`; cập nhật `loan.months_paid`.
- `dueTodayUnremindedPeriods(int $userId): Collection` — phục vụ Telegram.
> Tất cả thao tác trên model `LoanCustomSchedule` (bảng `loan_custom_schedules`).

### 3.4. Sửa `CreateLoan` command + handler
`src/Wallets/Lending/Application/Command/CreateLoan.php`
- Thêm field: `?float $receivedAmount = null`.

`CreateLoanHandler`:
- Điều kiện ghi giao dịch ví: `recordCashFlow == true` **VÀ** `Carbon::parse(started_at)->isToday()`. (Nếu không phải hôm nay → **bỏ qua** ghi ví dù client gửi cờ.)
- Số tiền giao dịch = `receivedAmount ?? principal_amount` (dùng `LoanWalletService::recordCreation` nhưng cho phép override amount → thêm tham số `?float $amount`).
- Sau khi tạo loan: gọi `LoanScheduleGenerator::generate($loan, backfillPast: true)`.
- **Bỏ/ngưng** auto-tạo `recurring_item` để nhắc (nguồn nhắc mới = periods). Giữ code cũ sau cờ để không phá dữ liệu cũ, hoặc migrate off — xem Quyết định.

### 3.5. Sửa `RecordLoanPaymentHandler`
- Sau khi tạo `Payment` + giao dịch ví (giữ nguyên `LoanWalletService::recordPayment`), gọi `LoanScheduleStateService::linkPaymentToPeriod($payment, $data['period_id'] ?? null)`.
- `RecordLoanPayment` command + form `storePayment`: thêm `period_id` (nullable) để trả đúng kỳ được nhắc.

### 3.6. Reminder cho dashboard (period-based)
- Sửa `LoanPaymentReminderService::upcomingLoanPayments` **hoặc** thêm `upcomingPeriods(int $userId, int $withinDays = 7)`:
  - Query `loan_custom_schedules` where `status in (pending,due,overdue)` and `due_date <= today + withinDays` (bao gồm cả quá hạn — hiển thị "Quá hạn N ngày").
- Sửa `GetDashboardOverviewHandler::buildUpcomingReminders` để tiêu thụ nguồn mới (thay dần recurring-based).

## 4. Telegram

### 4.1. Hạ tầng gửi tin
- `src/Wallets/Notification/Infrastructure/TelegramNotifier.php` (hoặc `app/Services/TelegramNotifier.php`):
  - `send(string $chatId, string $text, ?array $inlineKeyboard = null): bool` dùng `Illuminate\Support\Facades\Http` gọi `https://api.telegram.org/bot{TOKEN}/sendMessage` (parse_mode=HTML, kèm nút "Tạo khoản trả" là URL button).
  - Đọc token từ `config('services.telegram.bot_token')`.
- Cấu hình per-user chat_id qua `Setting`.

### 4.2. Link tạo khoản trả
- Nút Telegram trỏ tới URL tuyệt đối: `route('loans.show', $loanId) . '?pay_period=' . $periodId`.
- `loans/show.blade.php`: nếu có `?pay_period=` → auto mở modal ghi thanh toán, prefill `period_id`, `amount = expected_amount`, để user chỉ chọn ví + xác nhận.

### 4.3. Lệnh + lịch chạy
- Lệnh mới `app/Console/Commands/ProcessLoanPeriodsCommand.php` (`loans:process-periods`):
  1. `LoanScheduleStateService::transitionStatuses()` (cập nhật due/overdue).
  2. Gửi Telegram cho kỳ `due_date == today`, chưa trả, `reminded_telegram_at` null (hoặc chưa gửi hôm nay); set `reminded_telegram_at = now()`.
- `routes/console.php`: `Schedule::command('loans:process-periods')->dailyAt('07:00');` (giữ hoặc thay `loans:sync-payment-periods`).

### 4.4. Lệnh sinh/backfill kỳ (chạy tay khi deploy)
- `loans:generate-periods {--backfill} {--loan=}` gọi `LoanScheduleGenerator` cho loan hiện có.

## 5. Frontend (`resources/views/loans/create.blade.php`)

- Alpine `loanForm()`:
  - Thêm `startsToday()` so sánh `startDate` với hôm nay (parse dd/mm/yyyy).
  - Khối "Dòng tiền qua ví" **chỉ hiện** khi `startsToday()`; nếu không phải hôm nay → ẩn + không gửi `record_cash_flow`.
  - Thêm input **"Số tiền thực nhận"** (`received_amount`, money-input), mặc định = `principal`, chỉ hiện khi `recordCashFlow && startsToday()`.
- Controller `LoanController::store`: validate `received_amount` (nullable|numeric|min:0), truyền vào `CreateLoan(receivedAmount: ...)`; chỉ set `recordCashFlow` true khi start hôm nay.
- `loans/show.blade.php`: hỗ trợ `?pay_period=` auto-mở modal + prefill.
- Settings (`settings.index` + `SettingController` + `UpdateSettings`): thêm ô nhập **Telegram chat_id** + bật/tắt + nút "Gửi thử".

## 6. Đăng ký & wiring

- `WalletsServiceProvider::commandMap()`: đăng ký handler mới nếu tạo command mới (vd `GenerateLoanPeriods`), và cập nhật `UpdateSettings` để nhận chat_id.
- Bind `TelegramNotifier` (interface nếu muốn test double).
- Cập nhật `GetSettings`/`UpdateSettings` (module Preferences) để đọc/ghi `telegram_chat_id`, `telegram_enabled`.

## 7. Ràng buộc (cứng/mềm)

- **Cứng**: payment ảnh hưởng ví ⇒ bắt buộc `wallet_id` + tạo `transaction`; **xóa/hoàn payment phải đảo ngược giao dịch ví** (bổ sung luồng `DeleteLoanPayment` — hiện chưa có, rủi ro lệch số dư). Không trả vượt dư nợ.
- **Mềm**: ngày đến hạn, chọn ví lúc tạo, số tiền trả có thể ≠ kỳ vọng (ghi phần thừa/thiếu vào note; kỳ vẫn đánh dấu paid).

## 8. Kiểm thử (`tests/Feature/Lending`)

- `CreateLoanTest`:
  - start = hôm nay + nhận tiền ⇒ có transaction đúng `received_amount`.
  - start ≠ hôm nay ⇒ **không** transaction; kỳ quá khứ = `paid`, tương lai = `pending`.
- `RecordPaymentLinksPeriodTest`: trả ⇒ kỳ chuyển `paid`, gán `payment_id`, ví bị trừ đúng.
- `PeriodStatusTransitionTest`: due/overdue chuyển đúng theo ngày (Carbon::setTestNow).
- `TelegramReminderTest`: kỳ đến hạn hôm nay chưa trả ⇒ gọi `TelegramNotifier::send` (fake); set `reminded_telegram_at`; không gửi trùng.
- `DashboardUpcomingPeriodsTest`: kỳ trong 7 ngày + quá hạn xuất hiện.

## 9. Thứ tự triển khai (phases)

1. **P1 – Dữ liệu**: migration ALTER `loan_custom_schedules` (thêm `due_date/status/payment_id/paid_amount/user_id/reminded_telegram_at` + fix `paid_at`) + Setting/telegram config + mở rộng model `LoanCustomSchedule`.
2. **P2 – Sinh kỳ**: `LoanScheduleGenerator` + lệnh `loans:generate-periods --backfill` + tích hợp vào `CreateLoanHandler`.
3. **P3 – Tạo khoản vay theo start-date**: sửa command/handler/controller/blade (nhận tiền vào ví chỉ khi hôm nay + số thực nhận).
4. **P4 – Trả & liên kết kỳ**: `LoanScheduleStateService::linkPaymentToPeriod` + sửa `RecordLoanPaymentHandler` + `period_id` ở form.
5. **P5 – Nhắc dashboard** theo periods.
6. **P6 – Telegram**: notifier + settings + lệnh `loans:process-periods` + schedule + link auto-mở modal.
7. **P7 – Hoàn tác payment** (đảo ngược ví) — tăng độ an toàn.
8. **P8 – Tests** + cập nhật `docs/LOAN_PAYMENT_PERIODS.md`.

## 10. Cần bạn quyết (trước khi code)

1. **borrow/lend có sinh kỳ tự động không?** (đề xuất: chỉ bank sinh kỳ auto; borrow/lend tạo 1 kỳ đến hạn nếu có ngày hẹn, còn lại nhắc thủ công).
2. **recurring_items "Trả vay: …" cũ**: ngưng tạo mới và để nguồn nhắc chuyển hẳn sang periods? (đề xuất: ngưng tạo mới, giữ dữ liệu cũ, ẩn khỏi nhắc để tránh trùng).
3. **Telegram token**: 1 bot dùng chung (env) + chat_id theo user — đúng ý bạn chứ?
4. **Giờ chạy cron nhắc**: 07:00 hằng ngày ổn không?
5. **Số tiền thực nhận ≠ gốc**: có cần lưu cột `disbursed_amount` trên loan để đối chiếu không, hay chỉ cần đúng số tiền vào ví?
