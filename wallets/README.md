# Wallets

Vue 3 + TypeScript strict frontend for the Rust Wallets module. The screens, routes and navigation are ported from `~/www/wallets.nvnhan0810.com/resources/js`; the Laravel source is untouched. Domain helpers, JSON navigation/form use cases, HTTP/Identity adapters and Vue presentation live in separate directories under `src/`.

## Local development

Use Node 22.18+ or 24.12+. From this directory:

```sh
npm ci
cp .env.example .env
npm run dev
```

Open `http://localhost:5176`. Set `VITE_API_BASE_URL` to the gateway origin, without a path. Vite variables are public build-time settings; never place secrets here. SheetJS is pinned to its official CDN release, as documented at https://docs.sheetjs.com/docs/getting-started/installation/nodejs/ .

The backend must have its shared migrations applied, and Identity must register a **public** `wallets-web` client with `profile` scope, origin `http://localhost:5176`, and exact callback `http://localhost:5176/auth/callback`. The existing local `console db:seed` includes this registration; do not run local seeds against production. Production needs its own exact origin/callback registration. Authentication uses Authorization Code + PKCE S256, in-memory access tokens and Identity's HttpOnly refresh cookie.

## Validation

```sh
npm run lint
npm run format:check
npm test
npm run build
```

`npm run check` runs all checks. Build output is `dist/`; hosting must serve `index.html` for application routes, including `/auth/callback`, and serve static assets normally.

The page rendering tests use representative fixtures emitted by the real Rust module. Regenerate after changing the API contract:

```sh
cd ../backend
cargo run -q -p wallets --example page_contract > ../wallets/src/__tests__/fixtures/pages.json
cargo test -p wallets
```

Holiday imports accept CSV/TXT/XLS/XLSX, up to 5 MB and 10,000 rows. The first sheet needs `date`, `name`, `type` columns; use ISO `YYYY-MM-DD` or `DD/MM/YYYY` dates and `public`, `bank`, `custom` types. Download `/holiday-template.csv` for an example. Imported rows are validated by the Rust API inside the account transaction.

## Backend operations

The shared migration runner creates typed finance tables (`wallets`, `transactions`, `loans`, `loan_periods`, `loan_payments`, recurring tables and debt goals) and a shared `holidays` calendar. The previous `wallets_accounts` document table is migrated and removed. The gateway mounts `/wallets/pages` and `/wallets/actions`; the owner comes from Identity authentication. For the legacy Laravel import command and schema upgrade/rollback instructions, see [database migration](../docs/wallets-database-migration.md).

Scheduled console commands: `wallets:process-periods`, `wallets:auto-pay-due`, `wallets:process-reminders`. Configure cron separately; the old auto-pay schedule was 23:00 Asia/Ho_Chi_Minh. Telegram delivery requires backend `TELEGRAM_BOT_TOKEN`, `WALLETS_WEB_ORIGIN`, and enabled user settings with a chat ID. Reading pages never sends Telegram messages.

No migration, seed, cron delivery, data import or deployment is performed by frontend build/tests. A browser login and database integration check still require a running gateway, Identity, PostgreSQL and Redis.
