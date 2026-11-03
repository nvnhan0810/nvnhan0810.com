# Todo

Port of `~/www/todo.nvnhan0810.com` into the monorepo. Frontend: Vue 3, TypeScript strict, Vue Router, Vite and Lucide. Backend: Rust/Axum, SQLx/PostgreSQL and shared Identity OAuth PKCE. The old Laravel/React source is unchanged.

Implemented: projects and task CRUD with soft deletion; project/status/text filters and pagination; four Eisenhower quadrants with drag/drop and keyboard-accessible move controls; bulk backlog promotion; completion and classification audit logs; Pomodoro start, pause, resume, skip, reset, settings and active task; authenticated live sync; optional sound, screen wake lock, themes and Vietnamese/English labels; PWA and Web Push.

Navigation stays mounted during route/data changes. Loading is a thin, nonblocking bar. Forms keep entered values and field validation errors on failure.

## Local setup

Run the shared backend migrations and local seed from `backend/`:

```sh
cargo run -p console --bin migrate -- up
cargo run -p console --bin console -- seed
cargo run -p api-gateway
```

Seed registers `todos-web`, origin `http://localhost:5177`, callback `http://localhost:5177/auth/callback`. No Identity users are fabricated. For production, register a public Identity client with the deployed Todo origin and exact callback; set its ID below.

From `todos/`:

```sh
cp .env.example .env
npm ci
npm run dev
```

Set `VITE_API_BASE_URL` to the same gateway origin used by the other apps. `.env.example` matches their `http://localhost:8088` default; direct gateway default port is 8080, so change it when running without a reverse proxy. Production hosting must serve `dist/index.html` as the history fallback, including `/auth/callback`.

```sh
npm run check
```

## Database and background work

See [Todo feature port](../docs/todos-feature-port.md) for schema, API and deployment details. The migration creates relational tables; there is no per-user JSON document. This port does not import the old database or execute migrations against live databases automatically.

Pomodoro deadlines live in PostgreSQL. Start the shared worker so phases and notifications advance even when no browser is open:

```sh
cargo run -p console --bin console -- queue:work
```

Web Push is optional. Configure `TODO_VAPID_PUBLIC_KEY`, `TODO_VAPID_PRIVATE_KEY` (URL-safe base64 of the EC public/private keys), `TODO_VAPID_SUBJECT` and `TODO_WEB_ORIGIN` in the backend environment. Existing Laravel VAPID key values can be reused; the browser must subscribe on the new deployment. Never put the private key in `VITE_*` variables. With no keys, task management, sync and Pomodoro still work; the notification button explains that the server is not configured.

Browser notifications need HTTPS (or localhost), explicit user permission, and Web Push support. iOS requires an installed PWA. Sound and screen wake lock depend on browser support.
