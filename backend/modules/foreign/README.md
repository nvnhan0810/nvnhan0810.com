# Foreign module

Rust/Axum module mounted by API Gateway at `/foreign`.

`GET /foreign/` returns a public demo response:

```json
{ "module": "foreign", "message": "Foreign API is ready." }
```

Layers: `domain`, `application`, `infrastructure`, `presentation/http`.
Only the demo endpoint is implemented. No business logic, schema migration,
seeding or data import is included in this scaffold.

Run through the existing API Gateway with its normal Identity/DB configuration:

```sh
cd backend
cargo run --locked -p api-gateway
curl http://localhost:8080/foreign/
```

Use the exposed gateway port if running through Docker or port forwarding.
