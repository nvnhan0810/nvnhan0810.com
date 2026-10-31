# Foreign module

Rust/Axum module mounted by API Gateway at `/foreign`.

`GET /foreign/` returns a public demo response:

```json
{ "module": "foreign", "message": "Foreign API is ready." }
```

Layers: `domain`, `application`, `infrastructure`, `presentation/http`.
Demo and read-only English dictionary lookup are implemented. No business logic, schema migration,
seeding or data import is included in this scaffold.

Run through the existing API Gateway with its normal Identity/DB configuration:

```sh
cd backend
cargo run --locked -p api-gateway
curl http://localhost:8080/foreign/
```

Use the exposed gateway port if running through Docker or port forwarding.

## Read-only lookup

`GET /foreign/dictionary?q=hello` is public, returns an array of dictionary entries
with `word`, `phonetic`, `phonetics` and `meanings` (provider `partOfSpeech`,
definitions/examples/synonyms/antonyms). It calls https://dictionaryapi.dev/;
no DB writes, user vocabulary, editing or migration.

Errors: 400 `invalid_word`, 404 `word_not_found`, 502 `dictionary_unavailable`,
503 `dictionary_busy`. Missing `q` is rejected by Axum's query extractor.
Input: 1–100 ASCII bytes after trimming, letters/spaces/apostrophes/hyphens.
Provider: HTTPS fixed host, no redirects, 8-second timeout, 2 MiB response limit,
8 concurrent requests; gateway rate limiting still applies. No persistent cache.

Validation: `cargo test --locked -p foreign`, `cargo check --locked -p api-gateway`.
Live provider/audio and browser testing remain deployment checks.
