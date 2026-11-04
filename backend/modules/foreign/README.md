# Foreign module

Rust/Axum module mounted by API Gateway at `/foreign`.

`GET /foreign/` returns a public demo response:

```json
{ "module": "foreign", "message": "Foreign API is ready." }
```

Layers: `domain`, `application`, `infrastructure`, `presentation/http`.
Dictionary, owned vocabulary, Quiz, four Word Games, media/transcripts and profile history are implemented. Legacy database import has been removed.

Run through the existing API Gateway with its normal Identity/DB configuration:

```sh
cd backend
cargo run --locked -p api-gateway
curl http://localhost:8080/foreign/
```

Use the exposed gateway port if running through Docker or port forwarding.

## Read-only lookup

`GET /foreign/dictionary?q=hello` requires an Identity Bearer token with profile
scope, active grant and valid user; returns an array of dictionary entries
with `word`, `phonetic`, `phonetics` and `meanings` (provider `partOfSpeech`,
definitions/examples/synonyms/antonyms). It tries dictionaryapi.dev → freedictionaryapi.com → api.suvankar.cc;
lookup itself does not write the DB. Separate vocabulary endpoints save/list owned words; editing remains unimplemented.

Errors: 400 `invalid_word`, 404 `word_not_found`, 502 `dictionary_unavailable`,
503 `dictionary_busy`. Missing `q` is rejected by Axum's query extractor.
Input: 1–100 ASCII bytes after trimming, letters/spaces/apostrophes/hyphens.
Provider: HTTPS fixed host, no redirects, 3-second timeout per provider, 2 MiB response limit,
8 concurrent requests; gateway rate limiting still applies. No persistent cache.

Validation: `cargo test --locked -p foreign`, `cargo check --locked -p api-gateway`.
Live provider/audio and browser testing remain deployment checks.

Unavailable upstreams cool down for 15 minutes (429: 1 hour), in process memory.
Not-found also falls through to the next provider. If any provider is unavailable
and none returns a result, respond 502 rather than claim a definitive 404.
Provider contracts 2/3 are ported from legacy Laravel adapters, covered by fixtures;
live production verification remains outstanding. Auth errors preserve HTTP status.

## Foreign features

Foreign giữ các page mobile từ source Vue cũ; các override bố cục laptop/tablet bắt đầu tại 768px. Vocabulary có list, page detail, phát âm, xóa và chỉnh meanings qua JSON tại Lookup. Quiz và Scramble/Wordle/Hangman/Word Search nằm cùng hub; server giữ đáp án, round ID, chấm điểm và lịch sử. Media có YouTube preview/player, fetch captions, transcript editor/sync và mark listened. Profile có thống kê, lịch sử, theme và logout qua Identity.

API JSON `GET /foreign/pages?path=...`, `POST /foreign/actions` và `POST /foreign/media/preview` dùng Bearer Identity, owner lấy từ token. Public dictionary/save/list API hiện có vẫn giữ. Không có thông báo, admin hay AI/Listening assessment.

Đã bỏ command/API/cấu hình import DB cũ. Migration đã deploy được giữ nguyên checksum; migration mới xóa bảng import và thêm schema tính năng. Không tự chuyển dữ liệu hoặc chạy DB production.

## Transcript queue: Apalis + PostgreSQL

API requests publish typed `TranscriptTask` jobs to PostgreSQL. All backend consumers,
including transcript and Telegram alerts, run through `console queue:work`. The API
never starts a transcript consumer. See [shared queue setup](../../README.md#backend-queue-postgresql--supervisor).

Run `migrate up` before starting API or queue processes. Apalis owns the `apalis`
schema, task acknowledgements, heartbeats and orphan recovery. Both processes use
identical DB_* configuration; Redis is no longer required. Separate databases
isolate deployment environments.

Foreign job configuration remains in `src/config.rs`: automatic work uses normal
priority, manual Fetch captions uses high priority, and the high/normal/low lanes
reserve 2/1/1 workers per channel. These are internal queues, with one shared
backend process. Batch size is 1. Handler failures retry twice with 30/60-second
backoff (three total attempts).

Media JSON records the task token and visible status/error. Manual edits and newer
requests fence stale results. The job and media task token are committed in one PostgreSQL transaction.
Media creation precedes dispatch; dispatch errors propagate to the request, and
Fetch captions can retry the saved media. Existing videos can be queued using Fetch captions; no
historical backfill is run automatically.

The shared command handles SIGINT/SIGTERM and drains transcript workers for up to
60 seconds before closing the shared PostgreSQL pool. There is no public queue
dashboard.

## Saved YouTube channels

Open Listen → YouTube channels (`/youtube/channels` in the Foreign app).
Save a channel URL, `@handle`, channel ID, or legacy `/user/name` URL.
Each user owns their channel list (up to 20 channels); removing a channel keeps
videos already saved in listening. Browse all saved channels or filter one channel,
with Previous/Next pagination (24 entries per page). Shorts and live streams are excluded using the channel Videos-tab playlist
(`UULF`); private/deleted entries are omitted. Existing channels use this playlist
automatically without re-saving. Listening videos show Saved and link to their media detail page.
Adding a video uses the existing transcript queue. Duplicate imports, including
simultaneous imports through the manual YouTube form, reuse the user's existing
media record. Historical duplicates remain intact.

Setup:

1. Enable **YouTube Data API v3** in a Google Cloud project and create an API key.
2. Set `GOOGLE_API_KEY` in `backend/.env` or the API process environment. Restrict
   the key to YouTube Data API v3 (and server IPs when available). Never put it in
   frontend/Vite variables. Restart the API after changing the key.
3. Apply backend migrations using `cargo run --locked -p console --bin migrate -- up` before
   serving these endpoints. Start the shared queue worker for captions as usual.

The catalog uses `channels.list`, `playlistItems.list` on the Videos-tab playlist,
and `videos.list` to validate selected videos: 1 quota unit per request, with a
default shared allowance of 10,000 units/project/day for these endpoints. An
all-channel page makes one request per active channel; a single-channel page
makes one. Adding a video makes one validation request. No `search.list` calls or
paid third-party services are used. Quota is shared by all users; exhaustion shows
a retry-later message. See [Google's quota table](https://developers.google.com/youtube/data/determine_quota_cost).

Authenticated endpoints:

- `GET/POST /foreign/youtube/channels` (`POST`: `{ "url": "@handle" }`).
- `DELETE /foreign/youtube/channels/{channel_id}`.
- `GET /foreign/youtube/videos?channel_id=...&cursor=...` (both optional).
- `POST /foreign/youtube/videos/{video_id}/listening` with
  `{ "difficulty": "beginner|intermediate|advanced" }`.

Cursors retain each channel's upstream page token and consumed offset. Changing
the channel filter resets pagination. Refresh starts at the newest uploads;
upstream playlist changes can shift an in-progress browse session. If channels
change in another tab, refresh to reset an expired cursor. The key is only sent
to fixed Google HTTPS endpoints with timeouts and bounded responses.
Live Google API and PostgreSQL migration checks require a configured deployment;
unit/component tests use fixtures and do not call YouTube.

The Videos-tab playlist ID is derived as `UULF` + the channel ID without `UC`.
This is a YouTube convention rather than a documented Data API guarantee.
Missing Videos-tab playlists return an empty list; the API never falls back to
mixed uploads containing Shorts. Pagination from the previous mixed-upload
catalog expires; refresh once after upgrading to start video-only pagination.
