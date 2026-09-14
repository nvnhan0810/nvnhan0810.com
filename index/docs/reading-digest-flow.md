# Reading Digest — Flow chi tiết

Tài liệu mô tả pipeline **Reading Digest** hiện tại trong `index/` (sau khi refactor sang `modules/ReadingDigest` + Laravel Jobs/Controllers/Models).

---

## 1. Tổng quan

Reading Digest mỗi ngày (hoặc khi bấm **Fetch & send**):

1. **Fetch** bài từ các source (RSS / HN / Dev.to…)
2. **Enqueue enrich → embed** (async, gọi `ai.nvnhan0810.com`)
3. **Chọn bài** theo subject (retrieval + ranking)
4. **Gửi Telegram** 1 tin nhắn + 1 nút mở `/news/today`
5. User đọc / vote trên web → cập nhật sở thích cho lần sau

```text
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Trigger    │────▶│ RunDailyDigestJob│────▶│ SendDigestTelegram  │
│ schedule /  │     │ 1) Fetch all     │     │ Job                 │
│ Admin send  │     │ 2) Build digest  │     └──────────┬──────────┘
└─────────────┘     │ 3) dispatch TG   │                │
                    └────────┬─────────┘                ▼
                             │                 Telegram Bot API
                             │                 (1 link /news/today)
                             ▼
                    BatchEnrichArticleMetadataJob  (async, song song)
                             │
                             ▼
                    BatchEmbedArticlesJob          (async)
                             │
                             ▼
                    ai.nvnhan0810.com  (/api/enrich, /api/embed)
```

> **Lưu ý quan trọng:** Enrich/Embed chạy **bất đồng bộ** sau khi fetch.  
> Bước **Build digest** (retrieval + ranking) **không chờ** enrich/embed xong.  
> Lần chạy đầu tiên trong ngày có thể rank trên metadata/embedding cũ hoặc thiếu; lần sau mới “học” đủ từ AI.

---

## 2. Cách kích hoạt (Trigger)

### 2.1. Hiện tại: thủ công từ Admin

- Route: `POST /admin/reading-digest/send-now` (`admin.reading-digest.send-now`)
- Controller: `App\Http\Controllers\Admin\ReadingDigest\SettingsController::sendNow`
- Code: `Bus::dispatch(new RunDailyDigestJob)`

### 2.2. Scheduler (tự động mỗi ngày)

Đăng ký trong `bootstrap/app.php` → `withSchedule`:

| Job | Tần suất | Timezone |
|-----|----------|----------|
| `RunDailyDigestJob` | `dailyAt(DIGEST_NOTIFICATION_TIME)` — mặc định **07:00** | `DIGEST_TIMEZONE` — mặc định **Asia/Ho_Chi_Minh** |
| `PurgeStaleArticlesJob` | `dailyAt(03:00)` | cùng timezone |
| `DecayInterestScoresJob` | `weeklyOn(1, 04:00)` — mỗi **Thứ Hai** | cùng timezone |
| `RebuildUserEmbeddingJob` (không `userId` → tất cả user) | `dailyAt(04:30)` | cùng timezone |

Config:

- `DIGEST_NOTIFICATION_TIME=07:00`
- `DIGEST_TIMEZONE=Asia/Ho_Chi_Minh`
- `DIGEST_CONTENT_RETENTION_DAYS=30` — xoá bài **không có tương tác/view** sau N ngày

Production Docker đã chạy `schedule:work` qua Supervisor (xem secret.env.example k3s). Local: `php artisan schedule:work` hoặc cron `* * * * * php artisan schedule:run`.

### 2.3. Fetch một source riêng

- Admin Source → **Fetch now**: `FetchSourceJob::dispatch($sourceId)`
- Không chạy full digest / không gửi Telegram
- Chỉ fetch 1 source → enqueue enrich cho bài mới

---

## 3. Danh sách Jobs

Tất cả nằm trong `app/Jobs/ReadingDigest/`.

| Job | Handler (modules) | Vai trò |
|-----|-------------------|---------|
| **`RunDailyDigestJob`** | `FetchAllSourcesHandler` → `RunDailyDigestHandler` | Orchestrator chính: fetch all → build digest → dispatch Telegram |
| **`SendDigestTelegramJob`** | `SendDigestHandler` | Gửi 1 tin Telegram + stamp `telegram_sent_at` |
| **`FetchSourceJob`** | `FetchSourceHandler` | Fetch **1** source (admin) → dispatch enrich batch |
| **`BatchEnrichArticleMetadataJob`** | `BatchEnrichArticlesHandler` | Gọi AI `/api/enrich` theo batch → cập nhật summary/metadata → dispatch embed |
| **`BatchEmbedArticlesJob`** | `BatchEmbedArticlesHandler` | Gọi AI `/api/embed` → lưu `rd_article_embeddings` |
| **`EnrichArticleMetadataJob`** | `EnrichArticleHandler` | Enrich **1** bài (wrapper gọi batch handler) |
| **`EmbedArticleJob`** | `EmbedArticleHandler` | Embed **1** bài (wrapper gọi batch handler) |
| **`DecayInterestScoresJob`** | `DecayInterestScoresHandler` | Nhân hệ số decay lên interest scores |
| **`RebuildUserEmbeddingJob`** | `RebuildUserEmbeddingHandler` | Tính lại `user_embedding` từ bài liked/saved/… |
| **`PurgeStaleArticlesJob`** | `PurgeStaleArticlesHandler` | Xoá bài không tương tác sau `DIGEST_CONTENT_RETENTION_DAYS` (mặc định 30) |

Queue: `config('reading-digest.queue')` / env `DIGEST_QUEUE` (mặc định `default`).  
Worker cần chạy: `php artisan queue:work` (hoặc supervisor trong k8s).

---

## 4. Flow chính — `RunDailyDigestJob` (timeout 600s)

Thứ tự **bên trong** job này:

### Bước A — Fetch tất cả source (đồng bộ trong job)

`FetchAllSourcesHandler::handle()`:

1. **Purge** bài ngôn ngữ không thuộc `allowed_languages` (`en`, `vi`)
2. Lấy mọi `RdSource` `enabled = true`
3. Với mỗi source:
   - Tính `limit` bằng `SourceFetchLimitCalculator` (dựa demand subject × multiplier, clamp min/max)
   - `since` = now − `DIGEST_FETCH_SINCE_HOURS` (mặc định 24h)
   - `FetchSourceHandler` gọi adapter theo `source.type` (RSS / HN / Dev.to)
   - Lưu bài mới vào `rd_articles` (dedupe theo url/hash…)
4. Nếu có `article_ids` mới → **`BatchEnrichArticleMetadataJob::dispatch($ids)`** (async, không chờ)

Trả về stats: `sources`, `stored`, `purged`, `enriched_queued`, `errors`.

### Bước B — Build digest run (đồng bộ trong job)

`RunDailyDigestHandler::handle($userId)` với **user đầu tiên** trong DB (`User::orderBy('id')->first()`):

1. Tạo / lấy `RdUserReadingProfile` (+ default preferences)
2. Tạo `RdDigestRun` (`status = running`)
3. Với mỗi `RdSubject` enabled có gắn source:
   - **Retrieval** (`RetrievalService::retrieveForSubject`)
     - Lọc theo source của subject, language, freshness (`only_fetched_today`…), bỏ bài đã dismiss/dislike
     - Score bằng interest taxonomy + preferences → top `retrieval_candidates` (mặc định 30)
   - **Ranking** (`RankingService::rank`)
     - Nếu đủ candidate + có `GEMINI_API_KEY` → Gemini chọn top `articles_per_digest`
     - Không thì fallback theo retrieval score
   - Tạo `RdDigestRunItem` (rank, scores, `tracking_token` random 32)
4. Cập nhật run `status = completed` + stats

`RunDailyDigestJob` merge thêm `stats.fetch` từ bước A.

### Bước C — Gửi Telegram (async job tiếp theo)

`SendDigestTelegramJob::dispatch($run->id)`:

1. `SendDigestHandler` load `RdDigestRun`
2. `TelegramDigestNotifier::send($run)`:
   - Cần `DIGEST_TELEGRAM_ENABLED=true`, `BOT_TOKEN`, `CHAT_ID`
   - Nội dung: tiêu đề + ngày giờ (timezone settings) + số bài
   - **Một** URL: `{DIGEST_PUBLIC_URL}/news/today`
   - Inline button: `📰 Open today's digest` (bỏ qua nếu URL là localhost)
3. Thành công → `rd_digest_runs.telegram_sent_at = now()`

Không còn webhook / nút vote trên Telegram.

---

## 5. Nhánh async Enrich → Embed (sau fetch)

Chạy **song song / sau** bước build digest (không block Telegram).

```text
BatchEnrichArticleMetadataJob
        │
        ▼
BatchEnrichArticlesHandler
  • ArticleEnrichmentPolicy lọc bài đủ điều kiện
  • Chunk theo DIGEST_ENRICH_BATCH_SIZE
  • Mỗi bài: POST ai.nvnhan0810.com/api/enrich  (X-Api-Key)
  • Map: summary → rd_articles.summary
         tags → metadata.topics / style_tags (+ map taxonomy nếu match raw tags)
  • enriched_at = now()
        │
        ▼
BatchEmbedArticlesJob
        │
        ▼
BatchEmbedArticlesHandler → PgVectorEmbeddingStore → AiEmbeddingClient
  • POST /api/embed
  • Lưu vector vào rd_article_embeddings (+ pgvector nếu DB pgsql)
```

Env AI:

| Env | Ý nghĩa |
|-----|---------|
| `DIGEST_AI_BASE_URL` | Mặc định `https://ai.nvnhan0810.com` |
| `DIGEST_AI_API_KEY` | Header `X-Api-Key` |
| `DIGEST_AI_TIMEOUT` | Timeout HTTP (giây) |

Model/dimensions **không** cấu hình phía Laravel — AI service fix cứng.

---

## 6. Flow người dùng sau khi nhận Telegram

```text
Telegram message
      │
      ▼
https://…/news/today   (auth)
      │
      ├─ GET  /news/open/{tracking_token}  → ghi event "opened" → redirect URL gốc
      └─ POST /news/vote/{tracking_token}  → liked | disliked
                │
                ▼
        RecordInteractionHandler
          • rd_article_interactions
          • cập nhật rd_user_interest_scores theo taxonomy × weight
```

`tracking_token` trên `rd_digest_run_items` chỉ phục vụ **web** vote/open (không còn route `/reading-digest/a/{token}`).

---

## 7. Sơ đồ thứ tự Jobs (happy path full day)

```text
[Trigger] Admin send-now  **hoặc** schedule `RunDailyDigestJob` lúc 07:00 (Asia/Ho_Chi_Minh)

    │
    ▼
① RunDailyDigestJob                    ◀── queue worker
    │
    ├── (sync) FetchAllSourcesHandler
    │       └── dispatch ② BatchEnrichArticleMetadataJob   ──┐
    │                                                         │ async
    ├── (sync) RunDailyDigestHandler                          │
    │       ├── RetrievalService                              │
    │       └── RankingService (Gemini optional)              │
    │                                                         │
    └── dispatch ③ SendDigestTelegramJob                      │
                                                              │
② BatchEnrichArticleMetadataJob  ◀────────────────────────────┘
    └── (sync enrich AI)
        └── dispatch ④ BatchEmbedArticlesJob

③ SendDigestTelegramJob
    └── Telegram sendMessage → /news/today

④ BatchEmbedArticlesJob
    └── (sync embed AI) → rd_article_embeddings
```

Thứ tự **đảm bảo**:

1. `①` luôn trước `③` (cùng orchestration)
2. `②` sau khi fetch xong (dispatch từ A), có thể **chồng** với B và `③`
3. `④` luôn sau `②` (dispatch khi enrich xong)

Thứ tự **không đảm bảo**:

- Enrich/embed xong trước khi ranking — **không** chờ

---

## 8. Jobs bảo trì (không nằm trong daily send)

| Job | Khi nào | Việc |
|-----|---------|------|
| `DecayInterestScoresJob` | Schedule weekly (Thứ Hai 04:00) | `score *= interest_decay_factor` (0.98) |
| `RebuildUserEmbeddingJob` | Schedule daily 04:30 (không `userId` = tất cả); hoặc dispatch với `$userId` | Trung bình vector bài positive (liked/saved/finished) − negative |
| `FetchSourceJob` | Admin fetch 1 source | Fetch → enrich batch |
| `EnrichArticleMetadataJob` / `EmbedArticleJob` | Ít dùng / tiện ích 1 bài | Wrapper single-id |

---

## 9. Bảng DB chính liên quan flow

| Bảng | Vai trò |
|------|---------|
| `rd_sources` / `rd_subjects` / `rd_subject_source` | Cấu hình nguồn & chủ đề |
| `rd_articles` | Bài đã fetch (+ summary/metadata sau enrich) |
| `rd_article_embeddings` | Vector embedding |
| `rd_digest_runs` | 1 lần chạy digest / ngày / user |
| `rd_digest_run_items` | Bài được chọn + rank + `tracking_token` |
| `rd_digest_settings` | Giờ gửi / timezone |
| `rd_user_reading_profiles` | Preferences + `user_embedding` |
| `rd_user_interest_scores` | Điểm theo taxonomy node |
| `rd_article_interactions` | opened / liked / disliked / … |

---

## 10. File code “điểm vào” hữu ích

| Phần | Path |
|------|------|
| Jobs | `app/Jobs/ReadingDigest/*` |
| Admin trigger | `app/Http/Controllers/Admin/ReadingDigest/SettingsController.php` |
| Fetch all | `modules/ReadingDigest/Application/Handler/FetchAllSourcesHandler.php` |
| Build digest | `modules/ReadingDigest/Application/Handler/RunDailyDigestHandler.php` |
| Telegram | `modules/ReadingDigest/Infrastructure/Telegram/TelegramDigestNotifier.php` |
| AI client | `modules/ReadingDigest/Infrastructure/Ai/AiApiClient.php` |
| Config | `config/reading-digest.php` |
| Routes web | `routes/reading-digest.php` |

---

## 11. Checklist vận hành

1. Worker queue đang chạy (`queue:work` / k8s deployment).
2. Env Telegram: `DIGEST_TELEGRAM_ENABLED`, `BOT_TOKEN`, `CHAT_ID`, `DIGEST_PUBLIC_URL` (HTTPS public).
3. Env AI: `DIGEST_AI_BASE_URL`, `DIGEST_AI_API_KEY` (service `ai.nvnhan0810.com` healthy).
4. Optional ranking: `GEMINI_API_KEY`.
5. Subject đã **link** ít nhất 1 source enabled.
6. Scheduler đã đăng ký (`reading-digest:daily` @ 07:00 Asia/Ho_Chi_Minh); production cần `schedule:work` / cron đang chạy.

---

*Cập nhật theo codebase sau refactor modules + AI self-hosted (enrich/embed) + Telegram 1-link.*
