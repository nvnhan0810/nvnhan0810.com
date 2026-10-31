# Foreign module

Rust/Axum module mounted by API Gateway at `/foreign`.

`GET /foreign/` returns a public demo response:

```json
{ "module": "foreign", "message": "Foreign API is ready." }
```

Layers: `domain`, `application`, `infrastructure`, `presentation/http`.
Demo, dictionary lookup, saved vocabulary and a Laravel PostgreSQL vocabulary importer are implemented. See the runbook below for schema and import configuration.

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

## Saved vocabulary và import Laravel

Đã thêm Save word, Copy prompt AI (clipboard, không gọi AI), `/vocabulary`, command `foreign:import` và GET HTTP trigger tạm không token (vẫn có cờ enable), status root-only. Schema chạy qua console migrate tổng hợp. Runbook/config/API/phạm vi và các việc chưa làm: [foreign-vocabulary-import](../../../../docs/foreign-vocabulary-import.md).
