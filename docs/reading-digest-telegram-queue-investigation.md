# Reading Digest — Telegram không gửi trên production (điều tra)

Ngày điều tra: 2026-06-30 / 2026-07-01  
Môi trường: k3s namespace `nvnhan0810-com`, `QUEUE_CONNECTION=database`, 1 pod / 1 worker Supervisor.

## Triệu chứng

- Nút **Fetch & gửi ngay** (và schedule `RunDailyDigestJob`): digest chạy xong (`status=completed`, có items) nhưng **không nhận Telegram digest**.
- `telegram_sent_at` = `null`.
- `queue.log` trong pod thường **chỉ có** `RunDailyDigestJob RUNNING/DONE`, không có `SendDigestTelegramJob`.

## Môi trường đã xác nhận

| | Local (Sail) | Production (k3s) |
|---|---|---|
| Queue | `database` + `queue:work` tay | `database` + Supervisor `queue:work` |
| Nested dispatch | OK — enrich → send trong `queue.log` | Parent OK; job con **không** xuất hiện trong `queue.log` |
| `TELEGRAM_LOG_QUEUE` | `false` | `true` |
| `memory_limit` / worker memory | Cao hơn | **128M** (PHP + worker default 128MB) |
| Telegram digest config | Thường tắt local | `DIGEST_TELEGRAM_ENABLED=true` |

**Không phải nguyên nhân:** VPS `deploy.sh` cũ (không còn worker host); worker namespace khác ăn queue (`o4u` / `flc` dùng DB khác); k3s can thiệp `dispatch()`; override `Bus`/`Queue` trong app (đã grep — không có).

## Kiến trúc pipeline (trước fix)

```
sendNow / schedule
  → Bus::dispatch(RunDailyDigestJob)
  → worker: FetchAllSourcesHandler
       → EnrichArticleMetadataJob::dispatch() (nested)
  → RunDailyDigestHandler (rank, tạo run)
       → Bus::dispatch(SendDigestTelegramJob)  ← cuối handler
```

Dispatch gửi Telegram nằm **trong handler**, không phải cuối `RunDailyDigestJob::handle()`.

## Các giả thuyết đã loại trừ

| Giả thuyết | Kết quả |
|---|---|
| Local dùng `sync` queue | Sai — local cũng `database` |
| Nested `dispatch()` không INSERT `jobs` | **Sai** — instrument chứng minh INSERT OK (xem dưới) |
| `PendingDispatch` / chỉ `Job::dispatch()` hỏng | `Bus::dispatch` vẫn INSERT; vẫn không gửi được qua pipeline đầy đủ |
| Telegram API / config hỏng | Dispatch **top-level** `SendDigestTelegramJob` từ tinker → gửi OK (~13–15s) |
| Worker namespace khác cùng DB | Mỗi app DB riêng (`nvnhan0810`, `o4u`, `flc_nvnhan0810`) |
| Override Dispatcher / `JobProcessed` listener / `Bus::pipeThrough` | Không có trong codebase |

## Bằng chứng production (instrument `/tmp/digest-dispatch.log`)

Deploy instrument: đếm `jobs` / `send` (LIKE `%SendDigestTelegramJob%`) ngay trước và sau `Bus::dispatch` trong `RunDailyDigestHandler`.

**Lần chạy 2026-07-01 00:06 (pod `nvnhan0810-com-776cf479c8-jbcfl`):**

```
[2026-07-01T00:06:49+07:00] run=019f197e-f8a8-7011-9bef-428523e1f4a9 jobs=13→14 send=0→1 error=null
```

| Quan sát | Ý nghĩa |
|---|---|
| `send=0→1` | **INSERT thành công** — `SendDigestTelegramJob` có trong bảng `jobs` |
| `error=null` | Không exception khi dispatch |
| `jobs=13→14` | Tăng 1 row đúng lúc dispatch (lúc đó còn ~12 enrich + parent reserved) |

**Ngay sau `RunDailyDigestJob` DONE:**

- `queue.log`: chỉ 2 dòng parent — **không** có `EnrichArticleMetadataJob`, **không** có `SendDigestTelegramJob`
- `jobs` = **0**
- `telegram_sent_at` = **null**

**Dispatch top-level cùng run (tinker, 00:09):**

```
SendDigestTelegramJob  RUNNING → DONE (13 giây)
telegram_sent_at = 2026-07-01 00:09:33
```

## NGUYÊN NHÂN GỐC (đã chốt 2026-07-01)

**Bảng `jobs` trên Postgres production có cột `id` KHÔNG auto-increment** — `id bigint`, `default = NULL`, không sequence, không primary key. Nên **mọi job insert vào đều có `id = NULL`**.

Worker của Laravel xoá job đã chạy xong bằng `DELETE FROM jobs WHERE id = ?`. Với tất cả `id = NULL`, câu lệnh này thành `DELETE ... WHERE id IS NULL` → **xoá SẠCH mọi job đang chờ**. Vì vậy khi `RunDailyDigestJob` (parent) chạy xong và worker xoá nó, **tất cả job con vừa dispatch trong lúc parent chạy** (`EnrichArticleMetadataJob`, `SendDigestTelegramJob`, cả `SendTelegramLogJob`) **bị xoá luôn** — không chạy, không fail, `jobs → 0`, `telegram_sent_at = null`.

### Bằng chứng chốt

- `information_schema.columns`: `jobs.id` → `default = NULL`, `is_identity = NO`, `pg_get_serial_sequence('jobs','id') = null`.
- Insert 2 row test → cả hai `id = NULL`; `DELETE ... WHERE id = <first>` → **`deleted_rows = 2`** (xoá nhầm cả 2).
- Probe closure lồng nhau trong worker: `outer_txlevel = 0`, sau khi dispatch inner `jobs = 2 txlevel = 0` (đã commit) → nhưng inner **không bao giờ chạy**, `jobs → 0`. Không phải rollback, mà bị **xoá nhầm**.

### Vì sao các trường hợp khác “chạy được”

| Trường hợp | Lý do |
|---|---|
| Dispatch top-level (worker rảnh) | Chỉ có 1 row `id=NULL` → xoá đúng 1 row. OK. |
| `RunDailyDigestJob::handle()` chạy tay trong tinker | Parent **không** là row trong `jobs` → không có lệnh xoá nhầm job con. OK. |
| Bản “sync” gửi Telegram trong parent (08:00) | Gửi thẳng, **không qua queue** → không dính bảng `jobs` hỏng. |
| Local | Bảng `jobs` local tạo bằng migration chuẩn (`->id()`), `id` auto-increment đúng. |

**Không phải memory / OOM / TELEGRAM_LOG_QUEUE / nested dispatch / worker chết.** Toàn bộ DB production được import thiếu PK/sequence cho các cột id kiểu integer (đã từng vá cho `users`, `cache`, `cache_locks`), nhưng **`jobs` và `failed_jobs` bị bỏ sót**.

## Hướng xử lý (đã áp dụng + verify trên prod)

1. **Migration `2026_07_01_000000_ensure_queue_tables_constraints.php`**: tạo sequence + default `nextval` + primary key cho `jobs` và `failed_jobs` (idempotent, chỉ chạy khi thiếu PK). Đây là fix gốc.
2. **Giữ `SendDigestTelegramJob` dạng queued** (`RunDailyDigestJob` dispatch job con như cũ) — sau khi sửa bảng `jobs`, luồng queue chạy đúng.
3. Memory **không phải** nguyên nhân → `queue:work` để mặc định (đã rollback `-d memory_limit` / `--memory`).

**Verify prod (sau khi sửa `jobs.id`):** run `12:13:49` → `tg=2026-07-01 12:15:19`; `queue.log` chạy đủ `EnrichArticleMetadataJob`, `EmbedArticleJob`, `SendDigestTelegramJob`.

**Một câu:** Cột `jobs.id` không auto-increment nên mọi job `id=NULL`; worker xoá parent bằng `WHERE id=NULL` → xoá luôn mọi job con. Sửa PK/sequence cho bảng `jobs` là hết.

## Lịch sử debug (tóm tắt)

| Thời điểm | Ghi chú |
|---|---|
| 21:13 / 22:29 | Còn log debug + `TELEGRAM_LOG_QUEUE` — `RunDailyDigestJob` đôi khi không vào queue |
| 22:45 | Revert debug — parent chạy, send không |
| 23:04 | `Bus::dispatch` — parent OK, send không |
| 23:33 | Manual `RunDailyDigestJob` tinker — cùng pattern |
| 23:35 | Manual `SendDigestTelegramJob` — **gửi OK** |
| 00:06 | Instrument — **chốt INSERT OK**, pipeline vẫn không gửi |

## Cách reproduce instrument (nếu cần lại)

```bash
# Sau deploy code có ghi /tmp/digest-dispatch.log
kubectl exec -n nvnhan0810-com $POD -- rm -f /tmp/digest-dispatch.log
kubectl exec -n nvnhan0810-com $POD -- php artisan tinker --execute="Bus::dispatch(new App\Domains\ReadingDigest\Presentation\Jobs\RunDailyDigestJob());"
# Đợi parent DONE
kubectl exec -n nvnhan0810-com $POD -- cat /tmp/digest-dispatch.log
```

Đọc kết quả: `send=0→1` = INSERT OK; `send=0→0` = không INSERT.
