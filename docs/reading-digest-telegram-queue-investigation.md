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

## Kết luận điều tra

1. **Nested `Bus::dispatch(SendDigestTelegramJob)` ghi DB đúng** — không phải lỗi insert.
2. **Lỗi nằm sau INSERT:** sau khi parent job kết thúc (~30–60s, nhiều enrich pending), worker **không xử lý / không log** job con; queue rỗng nhưng Telegram chưa gửi.
3. **Gửi Telegram hoạt động** khi `SendDigestTelegramJob` được worker nhận ở **top-level** (worker rảnh, không phải tail của pipeline parent dài).
4. **Local khác production** ở workload (parent ~400ms vs ~30–60s LLM), `TELEGRAM_LOG_QUEUE`, và memory 128M — khả năng worker dừng hoặc không dequeue job con sau parent (cần thêm log memory nếu muốn chốt 100%).

**Một câu:** Production insert job gửi Telegram OK, nhưng worker không chạy job con sau parent; dispatch riêng từ tinker thì chạy và gửi OK.

## Hướng xử lý (đã áp dụng trong code)

Gọi **`SendDigestHandler` đồng bộ** ở cuối `RunDailyDigestJob::handle()` (sau khi merge stats fetch), **bỏ** queue `SendDigestTelegramJob` khỏi `RunDailyDigestHandler`.

- Digest + gửi Telegram là một luồng “send now” / daily — không cần thêm một tầng queue phụ thuộc worker dequeue sau job cha dài.
- `SendDigestTelegramJob` vẫn giữ cho trường hợp dispatch riêng nếu cần.

Tùy chọn infra: tăng `queue:work --memory=256` trong Supervisor nếu enrich job con vẫn cần queue ổn định.

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
