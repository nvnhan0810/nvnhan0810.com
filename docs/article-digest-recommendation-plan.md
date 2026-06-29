# Article Digest & Personalized Recommendation

> **Status:** Implemented. Product intent confirmed — link-only references from trusted external sites. Blog domain untouched.  
> **Goal:** Personal reading assistant that learns taste over time, fetches articles per **Subject**, ranks with taxonomy + embeddings + behavior + LLM, and sends a daily Telegram digest (default **08:00 Asia/Ho_Chi_Minh**).

---

## Product intent (confirmed)

This feature is **not** a second blog on our site. It is a **personal reading inbox + recommendation engine** for content that lives elsewhere on the web.

**Admin-driven setup only** — no hardcoded sources. You create **Subjects** (themes) and attach **Sources** (websites / RSS feeds) in admin.

### Daily flow (automatic)

At **notification time** from Settings (default 08:00):

1. **Fetch** — pull new articles from sources linked to enabled subjects (last 24h only)
2. **Enrich** — LLM metadata, taxonomy, embeddings (background queue)
3. **Rank** — score candidates per subject
4. **Telegram** — send digest with two links per article:
   - **📖 Read** — `/reading-digest/a/{token}` → redirect to original URL (counts opens)
   - **👍 Vote** — `/reading-digest/v/{token}` → feedback page (upvote / downvote / don&apos;t recommend / custom tags)

**Manual:** Settings → **Fetch & send now** runs the same pipeline immediately.

No background fetch every 30 minutes — only once daily before send (plus manual button).

---

## 0. Implementation Status

| Area | Status | Notes |
|------|--------|-------|
| DDD bounded context `ReadingDigest` | Done | All code under `app/Domains/ReadingDigest/` |
| `rd_*` migrations | Done | `2026_06_29_000001_create_reading_digest_tables.php` |
| Service provider + routes + scheduler | Done | Registered in `bootstrap/providers.php` |
| Taxonomy seeder | Done | `Database\Seeders\ReadingDigest\TaxonomySeeder` |
| Default profile seeder | Done | `Database\Seeders\ReadingDigest\DefaultProfileSeeder` |
| Admin UI (subjects, sources, settings, taxonomy, articles, today, profile) | Done | Inertia pages + header nav link |
| Fetch pipeline (RSS, HN Algolia) | Done | Reddit/github/custom reuse RSS adapter |
| Scheduled source fetch | Done | Once daily before digest only (not every 30 min) |
| LLM enrichment + ranking | Done | Via **Cursor API** (`CursorApiClient`) |
| Embeddings + pgvector | Done | JSON vector always; pgvector column optional on PostgreSQL |
| Hybrid retrieval + LLM ranking | Done | `RetrievalService` + `RankingService` |
| Telegram digest | Done | `TelegramDigestNotifier` + tracked links |
| Interest scores + decay job | Done | `RecordInteractionHandler`, weekly `DecayInterestScoresJob` |
| User embedding rebuild | Done | Nightly at 02:00 |
| Blog `/posts` unchanged | Done | No blog controller/model changes |

**Not yet implemented (future polish):**

- Pure domain entity classes (using Eloquent models directly)
- Domain events (`ArticleFetched`, `InteractionRecorded`)
- Separate Application Commands/Queries folders (handlers used instead)
- `rd_user_search_context` population from admin search
- LLM ranking cache by `(subject_id, date, profile_hash)`
- Content retention purge job (config exists, job not wired)
- Reddit-specific / GenericHtml adapters
- Telegram inline buttons
- Signed tracking tokens (plain random token today)
- Source CTR / style breakdown on profile dashboard
- Re-enrich action on article inbox

---

## 1. Problem Statement

Keyword search alone cannot reflect reading preferences such as:

- Prefer **deep dives / tutorials** over conference recaps or marketing posts
- Prefer **React, Go, Kubernetes** over Crypto or Politics
- Prefer **hands-on content** (code, benchmarks) over opinion pieces
- Shift focus short-term (e.g. “React Performance” this week) while keeping long-term interests

The system separates two concerns:

| Layer | Purpose |
|-------|---------|
| **Knowledge** | Store **references** to external articles (title, summary, URL, metadata) — not republished content |
| **User Preference** | Learn from explicit settings + implicit behavior (votes) to rank and recommend |

---

## 2. Domain Boundary (DDD) — Separate From Blog

This feature is a **new bounded context**. It does not modify or depend on the blog domain (`Post`, `Tag`, `Series`, `/posts`, public blog pages).

### 2.1 Bounded contexts

| Context | Scope | Touch during this feature? |
|---------|-------|----------------------------|
| **Blog** | `app/Models/Post.php`, tags, series, `/posts`, OG images | **No** — left unchanged |
| **ReadingDigest** | External articles, subjects, sources, preferences, digest, Telegram | **Yes** — all new code here |

### 2.2 Integration with the monolith (minimal)

Shared infrastructure only — no shared domain models:

| Shared | Usage |
|--------|-------|
| `User` + Google OAuth | Auth gate for admin & digest UI (`auth` middleware) |
| PostgreSQL | Separate tables with `rd_` prefix |
| Queue, scheduler, Inertia | Wired via `ReadingDigestServiceProvider` |
| Telegram env pattern | New `DIGEST_*` vars — not the logging channel |

**Anti-patterns avoided:**

- No reuse of blog `tags` / `post_tag` for article taxonomy
- Fetched articles stored as `rd_articles`, not `Post`
- No digest logic in blog controllers or migrations
- No coupling to blog post views

### 2.3 Actual folder layout (as built)

```
app/Domains/ReadingDigest/
├── Domain/
│   ├── Enums/                    # SourceType, InteractionEvent, ArticleType
│   ├── Repositories/             # SourceFetcherInterface
│   └── Services/                 # InterestScoreService, RetrievalScoringService
├── Application/
│   ├── DTOs/                     # FetchedArticleDTO
│   └── Handlers/                 # FetchSource, EnrichArticle, EmbedArticle, …
├── Infrastructure/
│   ├── Persistence/Eloquent/     # SubjectModel, DigestArticleModel, …
│   ├── Persistence/Repositories/ # RetrievalService, DefaultPreferences
│   ├── Sources/                  # RssSourceAdapter, HackerNewsAlgoliaAdapter, SourceFetcherRegistry
│   ├── Enrichment/               # OpenAiMetadataClient, RankingService, TaxonomyMapper
│   ├── Embeddings/               # OpenAiEmbeddingClient, PgVectorEmbeddingStore
│   ├── Llm/                      # CursorApiClient (OpenAI-compatible HTTP)
│   └── Telegram/                 # TelegramDigestNotifier
├── Presentation/
│   ├── Http/Controllers/         # Subject, Source, Settings, Taxonomy, …
│   └── Jobs/                     # FetchSourceJob, RunDailyDigestJob, …
└── ReadingDigestServiceProvider.php

routes/reading-digest.php
config/reading-digest.php

resources/ts/domains/reading-digest/
├── pages/admin/subjects/         # ListPage, FormPage
├── pages/admin/sources/          # ListPage, FormPage
├── pages/admin/settings/         # SettingsPage
├── pages/admin/taxonomy/         # TaxonomyPage
├── pages/admin/articles/         # ListPage
├── pages/admin/profile/          # ProfilePage
├── pages/admin/TodayPage.tsx
├── components/ReadingDigestNav.tsx
└── types/index.ts

database/migrations/2026_06_29_000001_create_reading_digest_tables.php
database/seeders/ReadingDigest/
├── TaxonomySeeder.php
└── DefaultProfileSeeder.php
```

Routes loaded only from `ReadingDigestServiceProvider` — not mixed into blog route groups.

Inertia resolves pages from both `resources/ts/pages/**` and `resources/ts/domains/**` (see `App.tsx` / `ssr.tsx`).

### 2.4 Table naming

All migrations use prefix **`rd_`**:

```
rd_subjects
rd_sources
rd_subject_source
rd_taxonomy_nodes
rd_source_tag_mappings
rd_articles
rd_article_taxonomy
rd_article_embeddings      # JSON vector + optional pgvector column
rd_article_interactions
rd_user_reading_profiles
rd_user_interest_scores
rd_user_search_context     # schema only; not populated yet
rd_digest_settings
rd_digest_runs
rd_digest_run_items
```

No overlap with `posts`, `tags`, `series`, `post_tag`.

---

## 3. Fit With Current Codebase

| Component | State | Usage |
|-----------|-------|-------|
| Laravel 11 + PostgreSQL | ✅ | Migrations, queue, scheduler via domain provider |
| Inertia + React (TS) | ✅ | Admin under `/admin/reading-digest/*` |
| Google OAuth auth | ✅ | Same `auth` middleware — blog unchanged |
| Queue worker | ✅ | Jobs on default queue (`DIGEST_QUEUE` configurable) |
| Telegram logging package | ✅ | Separate `DIGEST_TELEGRAM_*` for digest bot |
| Blog `/posts` | ✅ | Untouched |
| Scheduler | ✅ | Daily digest, weekly decay, nightly embedding rebuild |

---

## 4. Core Concepts

### 4.1 Subject

A **Subject** is a reading theme (e.g. “Frontend Deep Dives”). Each subject has:

- Display name, slug, optional description
- Linked **Sources** (many-to-many via `rd_subject_source`)
- `articles_per_digest`, `max_age_days`, `enabled`

Created manually in admin — no onboarding wizard.

### 4.2 Source

A **Source** is an external **website or publication** we monitor for new articles — e.g. `https://tuoitre.vn`, `https://thanhnien.com`, `https://dev.to`, or an RSS feed URL for a section of those sites.

| Field | Description |
|-------|-------------|
| `name` | Human label (e.g. “Tuổi Trẻ — Công nghệ”, “Dev.to RSS”) |
| `type` | **Fetch adapter** — how we pull links: `rss`, `hn_algolia`, `reddit`, `github_blog`, `custom_html` |
| `url` | Feed URL, site RSS endpoint, or API base (not necessarily the homepage) |
| `config` | JSON (e.g. HN `query`, `tags`) |
| `tag_mappings` | Raw tag → taxonomy node (admin UI) |

**Examples (product → typical setup):**

| Source (product) | Typical admin setup |
|------------------|---------------------|
| Tuổi Trẻ | Type `rss`, URL = category RSS feed from tuoitre.vn |
| Thanh Niên | Type `rss`, URL = section feed |
| dev.to | Type `rss`, URL = `https://dev.to/feed` or tag feed |
| Hacker News front page | Type `hn_algolia`, URL + query config |

**Implemented adapters:** `RssSourceAdapter`, `HackerNewsAlgoliaAdapter`. Reddit, GitHub blog, and custom HTML route through the RSS adapter until dedicated site scrapers exist.

Admin actions: CRUD, **Fetch now** (queues job), **Test fetch** (preview 5 items — title, summary, **original URL** only).

### 4.3 DigestArticle (reference card, not blog post)

Each fetched item is an `rd_articles` row — **not** blog `Post` and **not** a page we publish in full.

**What we keep:**

| Field | Purpose |
|-------|---------|
| `title`, `url` | Card display; **reading happens on the original site** |
| `summary` | Shown in inbox / Today / ranking; from feed or LLM |
| `content_text` / `content_html` | Optional; **internal** for embeddings and enrichment — not rendered as an on-site article |
| `metadata`, taxonomy, scores | Filtering, ranking, voting |

**What we show in UI:** summary, source name, scores, vote buttons, link out to publisher.

**Dedup:** Unique on `(source_id, external_id)` and `url_hash` (SHA-256 of normalized URL).

**Pipeline after fetch:**

1. `FetchSourceJob` → `FetchSourceHandler` → store article
2. `EnrichArticleMetadataJob` → LLM metadata + taxonomy mapping
3. `EmbedArticleJob` → vector stored in `rd_article_embeddings`

---

## 5. Taxonomy (Canonical Tag System)

Hierarchical taxonomy in **`rd_taxonomy_nodes`**, seeded by `TaxonomySeeder` (Programming/Frontend/React, AI/LLM, etc.) — separate from blog tags.

**Mapping pipeline (implemented):**

1. Source raw tags → `rd_source_tag_mappings` (rule-based)
2. LLM enrichment adds `taxonomy_paths` → resolved to nodes
3. `rd_article_taxonomy` pivot with `confidence`

Admin: `/admin/reading-digest/taxonomy` — add nodes with optional parent.

---

## 6. User Profile & Preference Model

Single-user initially (first authenticated Google user). Schema supports `user_id` on all preference tables.

### 6.1 Explicit preferences

Configured in **Settings** (`/admin/reading-digest/settings`) and stored in `rd_user_reading_profiles.preferences` (JSON).

Defaults from `DefaultPreferences::make()` — favorite topic paths, difficulty, languages, article types, disliked styles.

### 6.2 Interest scores (behavior)

`InterestScoreService` + `RecordInteractionHandler` update `rd_user_interest_scores` per taxonomy node, weighted by pivot confidence.

**Decay:** `DecayInterestScoresJob` weekly — `score * 0.98` (configurable).

Event weights in `config/reading-digest.php` → `interaction_weights`.

### 6.3 Short-term context

Table `rd_user_search_context` exists; **not populated yet**.

### 6.4 Style preferences from feedback

Today page sends interaction metadata with sentiment/reasons on 👍/👎.

---

## 7. Behavior Tracking

`rd_article_interactions` records events via `POST /reading-digest/interactions` (auth + throttle 60/min).

**Events:** `impression`, `opened`, `finished_reading`, `saved`, `liked`, `disliked`, `shared`, `dismissed`, `rated`, `rated_positive`, `rated_negative`

**Instrumentation:**

- Telegram links → `/reading-digest/a/{token}` (public redirect; records `opened` when user is authenticated)
- Today page → 👍 👎 💾 ✕ buttons

Handler: `RecordInteractionHandler` (not a separate command class).

---

## 8. Embeddings

### 8.1 Article embeddings

Text: `{title}\n\n{summary}\n\n{first_2000_chars}`

Stored in `rd_article_embeddings.vector` (JSON). On PostgreSQL with pgvector extension, an additional `embedding vector(1536)` column is created (optional — migration catches failures gracefully).

### 8.2 User taste vector

`RebuildUserEmbeddingJob` nightly at 02:00 — weighted average of vectors from saved/finished/liked articles (minus dismissed/disliked).

### 8.3 Retrieval similarity

`RetrievalScoringService` adds `cosine_similarity(user_vector, article.embedding) * 20` to hybrid score.

Infrastructure: `PgVectorEmbeddingStore`, `OpenAiEmbeddingClient` (calls Cursor API base URL).

**Note:** Embeddings require an OpenAI-compatible `/embeddings` endpoint on `CURSOR_API_BASE_URL`. If unavailable, retrieval falls back to taxonomy + behavior scoring only.

---

## 9. Two-Stage Recommendation Pipeline

Orchestrated by `RunDailyDigestHandler` (via `RunDailyDigestJob` or Settings → Send now).

### Stage 1 — Retrieval (~30 candidates)

`RetrievalService` per enabled subject:

1. Articles from subject’s sources
2. Hard filters: dismissed/disliked, ignored taxonomy, language, max age, recently sent (7 days)
3. Hybrid score via `RetrievalScoringService`
4. Top **K=30** (`retrieval_candidates`)

### Stage 2 — LLM Ranking (~5–10)

`RankingService` calls Cursor API chat completions with profile + candidate summaries.

- Skips LLM if &lt;5 candidates (uses retrieval order)
- Falls back to retrieval scores if API fails or `CURSOR_API_KEY` unset

Results → `rd_digest_run_items` with `retrieval_score`, `llm_score`, `llm_reason`, `tracking_token`.

**Not yet:** LLM response cache by `(subject_id, date, profile_hash)`.

---

## 10. Daily Telegram Digest

### 10.1 Schedule

- Default: **08:00** `Asia/Ho_Chi_Minh` (env + `rd_digest_settings` override)
- Registered in `ReadingDigestServiceProvider`

```php
Schedule::job(new RunDailyDigestJob)->dailyAt($time)->timezone($tz);
Schedule::job(new DecayInterestScoresJob)->weekly();
// RebuildUserEmbeddingJob dispatched daily at 02:00
```

**Production:** ensure cron runs `php artisan schedule:run` every minute.

### 10.2 Flow

```
RunDailyDigestJob
  → RunDailyDigestHandler (per subject: retrieve + rank)
  → Persist rd_digest_runs + rd_digest_run_items
  → SendDigestTelegramJob
  → TelegramDigestNotifier
```

### 10.3 Message format

Telegram lists **summaries + scores**; the link goes to the **original article** (via our tracking redirect for analytics):

```
📚 Daily Reading — Mon, 29 Jun

━━ Frontend Deep Dives ━━
1. (95) Deep dive into React Compiler internals
   React · Advanced · 12 min
   https://yoursite.com/reading-digest/a/{tracking_token}
   → redirects to https://dev.to/... (original publisher)
```

On **Today** / **Inbox**, the user sees the same model: card with summary and 👍/👎 — no full article body on our site.

### 10.4 Config

```env
DIGEST_TELEGRAM_ENABLED=true
DIGEST_TELEGRAM_BOT_TOKEN=
DIGEST_TELEGRAM_CHAT_ID=
```

Uses a **separate bot** from `TELEGRAM_*` logging channel vars.

---

## 11. Admin UX

All under **`/admin/reading-digest/*`**. Header nav: newspaper icon → Today.

| Route | Page | Features |
|-------|------|----------|
| `/admin/reading-digest/subjects` | List + CRUD | Attach sources, articles per digest, max age |
| `/admin/reading-digest/sources` | List + CRUD | Register sites/feeds (e.g. tuoitre.vn RSS), tag mappings, fetch now, test fetch |
| `/admin/reading-digest/settings` | Settings | Timezone, preferences JSON, send now, recent runs |
| `/admin/reading-digest/taxonomy` | Taxonomy | View tree, add nodes |
| `/admin/reading-digest/articles` | Inbox | Search fetched **references** (title, summary, link out); force include/exclude |
| `/admin/reading-digest/today` | Digest review | Summary cards, scores, 👍👎💾✕ — vote to train ranking |
| `/admin/reading-digest/profile` | Profile | Interest scores, reset profile |

Blog admin (`/admin/posts`, tags, series) unchanged.

---

## 12. Digest Interaction UX

### 12.1 Daily digest review

`/admin/reading-digest/today` — shows today’s `rd_digest_run_items` with LLM reason and scores.

### 12.2 Profile dashboard

`/admin/reading-digest/profile` — top interest scores + explicit preferences JSON. Reset clears scores and restores defaults.

### 12.3 Feedback loop (future copy)

> “Because you saved 3 React performance articles this week, React Performance +15.”

Not surfaced in UI yet; scores update in backend immediately.

---

## 13. Article Ingestion Pipeline

Jobs in `Presentation/Jobs/` dispatch application handlers:

| Job | Handler | Responsibility |
|-----|---------|----------------|
| `FetchSourceJob` | `FetchSourceHandler` | Pull raw items, dedup, queue enrich |
| `EnrichArticleMetadataJob` | `EnrichArticleHandler` | LLM metadata + taxonomy sync |
| `EmbedArticleJob` | `EmbedArticleHandler` | Store embedding vector |
| `RunDailyDigestJob` | `RunDailyDigestHandler` | Retrieve + rank all subjects |
| `SendDigestTelegramJob` | `SendDigestHandler` | Send Telegram message |
| `DecayInterestScoresJob` | `DecayInterestScoresHandler` | Weekly 0.98 decay |
| `RebuildUserEmbeddingJob` | `RebuildUserEmbeddingHandler` | Nightly user vector |

---

## 14. LLM Integration — Cursor API

All LLM HTTP calls go through **`Infrastructure/Llm/CursorApiClient`**, an OpenAI-compatible client:

| Endpoint | Used by |
|----------|---------|
| `{CURSOR_API_BASE_URL}/chat/completions` | Metadata enrichment, daily ranking |
| `{CURSOR_API_BASE_URL}/embeddings` | Article embeddings (if supported) |

Authentication: `Authorization: Bearer {CURSOR_API_KEY}`

**Default base URL:** `https://api.cursor.com/v1`

If you use a **local OpenAI-compatible proxy** for Cursor Composer:

```env
CURSOR_API_BASE_URL=http://127.0.0.1:8787/v1
```

Get API keys from [Cursor Dashboard → Integrations / API Keys](https://cursor.com/dashboard).

Implementation classes (legacy names retained):

- `OpenAiMetadataClient` — article enrichment
- `OpenAiEmbeddingClient` — embeddings
- `RankingService` — digest ranking

When `CURSOR_API_KEY` is unset, enrichment uses rule-based fallbacks and ranking uses retrieval scores only.

---

## 15. Database Schema (Summary)

See §2.4. Migration: `database/migrations/2026_06_29_000001_create_reading_digest_tables.php`.

**Indexes:**

- `rd_articles(published_at DESC)`
- `rd_articles(source_id, external_id)` unique
- `rd_articles(url_hash)` unique
- `rd_article_interactions(user_id, article_id, event)`
- pgvector IVFFlat index on `rd_article_embeddings.embedding` (PostgreSQL + extension only)

---

## 16. Routes Summary

Loaded from `routes/reading-digest.php`.

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/reading-digest/a/{token}` | Public | Track open → redirect to original article URL |
| GET/POST | `/reading-digest/v/{token}` | Yes | Vote page — upvote / downvote / dismiss / custom tags |
| POST | `/reading-digest/interactions` | Yes | Record interaction |
| GET/POST/PUT/DELETE | `/admin/reading-digest/subjects/*` | Yes | Subject CRUD |
| GET/POST/PUT/DELETE | `/admin/reading-digest/sources/*` | Yes | Source CRUD + fetch + test |
| GET/PUT | `/admin/reading-digest/settings` | Yes | Schedule + preferences |
| POST | `/admin/reading-digest/send-now` | Yes | Manual digest |
| POST | `/admin/reading-digest/preview` | Yes | Preview ranking (JSON) |
| GET | `/admin/reading-digest/today` | Yes | Digest review |
| GET/POST | `/admin/reading-digest/profile/*` | Yes | Profile + reset |
| GET/POST | `/admin/reading-digest/taxonomy` | Yes | Taxonomy manager |
| GET/PATCH | `/admin/reading-digest/articles/*` | Yes | Article inbox |

---

## 17. Configuration

`config/reading-digest.php`:

```php
return [
    'notification_time' => env('DIGEST_NOTIFICATION_TIME', '08:00'),
    'timezone' => env('DIGEST_TIMEZONE', config('app.timezone')),
    'articles_per_subject' => (int) env('DIGEST_ARTICLES_PER_SUBJECT', 5),
    'retrieval_candidates' => 30,
    'interest_decay_factor' => 0.98,
    'embedding_model' => env('DIGEST_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => (int) env('DIGEST_EMBEDDING_DIMENSIONS', 1536),
    'ranking_model' => env('DIGEST_RANKING_MODEL', 'composer-2.5'),
    'cursor_api_key' => env('CURSOR_API_KEY'),
    'cursor_api_base_url' => env('CURSOR_API_BASE_URL', 'https://api.cursor.com/v1'),
    'table_prefix' => 'rd_',
    'queue' => env('DIGEST_QUEUE', 'default'),
    'telegram' => [ /* DIGEST_TELEGRAM_* */ ],
    'interaction_weights' => [ /* event => weight */ ],
    'content_retention_days' => (int) env('DIGEST_CONTENT_RETENTION_DAYS', 90),
];
```

Admin UI overrides for schedule stored in `rd_digest_settings`.

### `.env` reference

```env
# Schedule
DIGEST_NOTIFICATION_TIME=08:00
DIGEST_TIMEZONE=Asia/Ho_Chi_Minh
DIGEST_ARTICLES_PER_SUBJECT=5

# Cursor API (OpenAI-compatible)
CURSOR_API_KEY=
CURSOR_API_BASE_URL=https://api.cursor.com/v1
DIGEST_RANKING_MODEL=composer-2.5
DIGEST_EMBEDDING_MODEL=text-embedding-3-small
DIGEST_EMBEDDING_DIMENSIONS=1536

# Telegram digest (separate from TELEGRAM_LOG_*)
DIGEST_TELEGRAM_ENABLED=false
DIGEST_TELEGRAM_BOT_TOKEN=
DIGEST_TELEGRAM_CHAT_ID=

# Optional
DIGEST_QUEUE=default
DIGEST_CONTENT_RETENTION_DAYS=90
```

---

## 18. Setup & Deployment

### First-time setup

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\ReadingDigest\\TaxonomySeeder
php artisan db:seed --class=Database\\Seeders\\ReadingDigest\\DefaultProfileSeeder
```

Ensure queue worker is running (`php artisan queue:work` or Supervisor).

Ensure scheduler cron is configured in production.

### Typical workflow

1. Log in via Google OAuth
2. Open **Reading Digest** from header (newspaper icon)
3. Review seeded **Taxonomy**; extend if needed
4. Create **Sources** — register external sites/feeds (e.g. Tuổi Trẻ RSS, dev.to feed, HN query); map raw tags
5. Create **Subjects**; attach sources (themes like “Vietnam tech news”, “Frontend deep dives”)
6. Click **Fetch** on sources (or wait for future scheduled fetch — manual today)
7. Open **Inbox** — skim summaries; links open the **original publisher**; toggle include/exclude if needed
8. Configure **Settings** (time, preferences); use **Send now** to test digest
9. Review **Today** — vote 👍/👎 on summary cards to train interest scores (no full article on our site)
10. Enable **Telegram** env vars for daily push (links still go to original articles)

### PostgreSQL pgvector (optional)

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

Migration attempts this automatically; JSON embeddings work without it.

---

## 19. Default Profile (Seeder)

`DefaultProfileSeeder` seeds `rd_user_reading_profiles` for the first user with sensible defaults (React/TS/Go/K8s/LLM favorites, advanced difficulty, avoid crypto/politics).

Subjects and sources: **create in admin**, not via seeder.

---

## 20. Security & Privacy

- Admin + interaction routes: `auth` middleware
- Article redirect `/reading-digest/a/{token}`: **public** (Telegram links); records `opened` only when session exists
- Interaction endpoint: rate limited (`throttle:60,1`)
- `DIGEST_TELEGRAM_*` and `CURSOR_API_KEY` not exposed to frontend
- Content retention config exists (`content_retention_days`); purge job not wired yet

---

## 21. Observability

- `rd_digest_runs` + `stats` JSON audit per run
- Settings page shows last 30 runs
- Failed Telegram: logged via `Log::error`; queue retry via Laravel job retries
- LLM failures: logged as warnings; graceful fallback to retrieval ranking

---

## 22. Implementation Phases (checklist)

### Phase 0 — Domain skeleton

- [x] `app/Domains/ReadingDigest/` + `ReadingDigestServiceProvider`
- [x] `routes/reading-digest.php` + `config/reading-digest.php`
- [x] `rd_*` migrations
- [x] Taxonomy seeder
- [x] Scheduler registration
- [x] Blog routes unchanged

### Phase 1 — Subjects & Sources

- [x] Admin CRUD (controllers + Inertia)
- [x] `FetchSourceHandler` + RSS/HN adapters
- [x] `rd_articles` storage + dedup

### Phase 2 — Metadata & Taxonomy

- [x] `EnrichArticleHandler` + LLM client
- [x] Source tag mapping admin
- [x] Taxonomy manager UI

### Phase 3 — Profile & Tracking

- [x] `rd_user_reading_profiles` + settings form
- [x] `RecordInteractionHandler` + interest scores
- [x] Digest review page
- [x] Profile dashboard (partial — no CTR/style breakdown yet)

### Phase 4 — Embeddings & Retrieval

- [x] pgvector (optional) + `EmbedArticleHandler`
- [x] User embedding rebuild job
- [x] Hybrid retrieval

### Phase 5 — LLM Ranking + Telegram

- [x] `RunDailyDigestHandler` + ranking
- [x] Telegram notifier + tracked links
- [x] Admin: send now, preview ranking API

### Phase 6 — Polish (remaining)

- [x] Interest decay job
- [ ] Short-term context from admin search
- [ ] Telegram inline buttons
- [ ] More source adapters (dedicated Reddit, GenericHtml)
- [ ] LLM ranking cache
- [ ] Content retention purge job
- [ ] Signed tracking tokens
- [x] Scheduled source fetch (daily before digest only)

---

## 23. Open Questions (resolved / open)

| Question | Resolution |
|----------|------------|
| LLM provider | **Cursor API** via OpenAI-compatible `CursorApiClient`; base URL configurable |
| pgvector | Optional on PostgreSQL; JSON fallback always available |
| Telegram format | One combined message, sections per subject |
| Article retention | Config `DIGEST_CONTENT_RETENTION_DAYS=90`; purge job not implemented |
| Queue name | Default queue; override with `DIGEST_QUEUE` |

---

## 24. Success Criteria

- [x] Blog `/posts` and existing admin unchanged
- [x] All digest code under `app/Domains/ReadingDigest/`
- [x] Admin can create subjects and sources without code changes
- [x] Admin can set digest time (default 08:00) and explicit preferences
- [x] Daily Telegram can list ranked articles per subject with reasons (when enabled)
- [x] Interactions update interest scores immediately
- [ ] After ~100 interactions, ranking favors deep technical content — **requires production usage to validate**

---

## 25. References

- Architecture: DDD bounded context `ReadingDigest`, no blog coupling, admin-driven setup (no onboarding wizard).
- LLM: Cursor API OpenAI-compatible endpoints (`/chat/completions`, `/embeddings`).
- Original spec: taxonomy, profile, behavior weights, embeddings, interest decay, two-stage retrieval + LLM ranking.
