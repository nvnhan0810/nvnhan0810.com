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

## Layout and PWA

Laptop/desktop (≥1024px) gets a fixed sidebar grouped into _Tài chính_, _Kế hoạch_ and _Cá nhân_. Phones and tablets get a top bar (back button and page title on sub-pages), section tabs on list pages, and a bottom bar: Tổng quan · Giao dịch · **+** · Ví · Thêm (a sheet with the other screens). The navigation is defined once in `src/application/app-navigation.ts`; shared component classes (`btn`, `card`, `form-input`, `chip`…) live in `src/styles.css`.

The app is installable as a PWA: `public/manifest.webmanifest`, icons in `public/icons/` (regenerate with `python3 scripts/generate-icons.py`, needs Pillow), and a service worker built to `dist/sw.js` by `pwa/vite-plugin.ts` from `pwa/service-worker.js`. The worker precaches the build, serves navigations network-first with an offline app-shell fallback, and never intercepts API/Identity or other cross-origin requests, so no account data is cached. It is only registered in production builds (`npm run build` + `npm run preview` to try it locally). Android/desktop Chrome get an install banner on the dashboard and a button under _Cá nhân → Ứng dụng_; iOS Safari shows the _Chia sẻ → Thêm vào MH chính_ hint.

Hosting requirements: serve over HTTPS, serve `/sw.js` and `/index.html` with `Cache-Control: no-cache`, serve `.webmanifest` as `application/manifest+json`, and keep hashed `/assets/*` immutable. The Identity client needs no change for the installed app; it uses the same origin and callback.

## Bank wallets, bank app handoff and VietQR

Bank wallets (`type = bank`) can store the bank, account number, holder and branch. The user only picks the bank; its BIN and code are filled in from the master data, never typed.

Bank master data:

- `src/domain/banking/vietqr-banks.json` is a verbatim copy of https://vietqr.app/banks.json. To update the bank list, download that file again and replace this one (it is excluded from Prettier so the download can be dropped in as is).
- `BANK_APPS` in `src/domain/banking/bank-list.ts` maps a bank code to its VietQR deeplink app id. Banks without an entry can still be picked, they just cannot open an app.

On a phone (Android/iOS), saving an expense or transfer from such a wallet then opens that bank's app through VietQR's `https://dl.vietqr.io/pay?app=<id>` router (app ids from `https://api.vietqr.io/v2/{android,ios}-app-deeplinks`); the user can untick this per transaction.

_Giao dịch → Quét QR_ (also in the mobile menu and the PWA shortcut `/transactions/create?scan=1`) reads a VietQR transfer code with the camera (native `BarcodeDetector`, else the lazy-loaded `jsqr`) or from a picture. The EMVCo payload (tag 38 NAPAS beneficiary, 54 amount, 59 name, 62/08 message) is checked against its CRC, the expense is prefilled, a bank wallet is picked as the source, and after saving the source bank app opens with `ba=<account>@<bank>`, `am` and `tn` prefilled. Apps marked `autofill` in the VietQR list fill these in; for the others the recipient account number is copied to the clipboard on save. The camera needs HTTPS (or `localhost`).

## Backend operations

The shared migration runner creates typed finance tables (`wallets`, `transactions`, `loans`, `loan_periods`, `loan_payments`, recurring tables and debt goals) and a shared `holidays` calendar. The previous `wallets_accounts` document table is migrated and removed. The gateway mounts `/wallets/pages` and `/wallets/actions`; the owner comes from Identity authentication. For the legacy Laravel import command and schema upgrade/rollback instructions, see [database migration](../docs/wallets-database-migration.md).

Scheduled console commands: `wallets:process-periods`, `wallets:auto-pay-due`, `wallets:process-reminders`. Configure cron separately; the old auto-pay schedule was 23:00 Asia/Ho_Chi_Minh. Telegram delivery requires backend `TELEGRAM_BOT_TOKEN`, `WALLETS_WEB_ORIGIN`, and enabled user settings with a chat ID. Reading pages never sends Telegram messages.

No migration, seed, cron delivery, data import or deployment is performed by frontend build/tests. A browser login and database integration check still require a running gateway, Identity, PostgreSQL and Redis.
