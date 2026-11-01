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

## Transcript queue: Apalis + Redis

Saving YouTube media pushes a typed `TranscriptTask` directly to `apalis-redis`. Apalis owns task storage, acknowledgement, heartbeat, orphan recovery and retries. There is no PostgreSQL queue table or outbox. Media JSON stores only the visible transcript status/error and the current task ID so manual edits and newer requests reject stale results. If dispatch fails, the saved media shows a retryable error; Save and Redis dispatch are not atomic.

Redis connection details are configured with `REDIS_URL` in `backend/.env.example`, including ACL/password/DB as needed; `rediss://` is supported by the client.

`REDIS_QUEUE_PREFIX` is a required global environment namespace (for example `dev` or `prod`). API and dedicated workers in the same environment must use the same value. Queue names are `<REDIS_QUEUE_PREFIX>:<module-prefix>:<channel>:<lane>`, for example `prod:foreign:queue:youtube-transcript:normal`.

Queue settings belong to Foreign and are maintained in `src/config.rs` (`QueueConfig::default()`), shared by the API and console worker:

- `prefix`: `foreign:queue`, isolating this app's Apalis keys on shared Redis.
- `dispatch.channel`: `youtube-transcript`; `channels` defaults to this dispatch channel.
- `dispatch.automatic`: `Priority::Normal` when Save commits.
- `dispatch.manual`: `Priority::High` for explicit Fetch captions/retry. Redis retries retain the original priority. Re-dispatch while active does not create/promote duplicates.
- `workers`: `[2, 1, 1]` reserved workers per channel for high/normal/low. Queues are `<REDIS_QUEUE_PREFIX>:<prefix>:<channel>:high`, `:normal`, `:low`. Priority is capacity-based, without strict ordering or preemption. Zero disables a lane.
- `api_worker_enabled`: `true`; disable when using a dedicated worker process.

Apalis retries a failed handler twice (three total attempts) with 30/60-second exponential backoff. Media state records errors and fences stale results; manual transcript edits cancel active work and cannot be overwritten. The API polls this state quietly while queued/running, so playback remains usable.

The API starts workers by default. For a dedicated process with the existing image, set `api_worker_enabled: false` in `src/config.rs` and run `console foreign:transcript-worker` (Docker override `--entrypoint /usr/local/bin/console`; local `make -C backend transcript-worker`). Both processes need the same DB and Redis configuration. Configure matching channels for dispatch and consumption. SIGTERM/SIGINT allow up to 60 seconds to drain workers.

Existing videos can be queued using Fetch captions; no automatic historical backfill is performed. This integration does not expose `apalis-board` or a public queue dashboard. No queue migrations are required.

Module hosts create `AppResources` once, then call `foreign::start(identity, resources.database.clone(), resources.redis.clone())` and mount the returned router. Identity receives the same PostgreSQL pool. Foreign owns queue configuration and embedded workers; it never closes the shared pool. The host obtains `shutdown_handle()` and calls its `request_shutdown()` when process shutdown begins, then awaits `shutdown()` after HTTP drains. Dedicated console workers use `foreign::start_worker(pool, redis)` with the same lifecycle. The host closes shared resources after all modules stop.

`SharedRedis` clones share one lazily initialized reconnecting connection manager per process. All replicas/processes use the same configured `REDIS_URL` but have their own network connections. Module prefixes isolate queue keys; each module may define multiple channels for different jobs. Each job type needs its own Apalis storage/handler; the current transcript worker consumes only transcript tasks on its configured channels. Redis connection settings and the environment prefix are global; module prefixes and job channel settings remain in module config.
