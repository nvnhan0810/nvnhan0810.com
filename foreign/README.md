# Foreign

Scaffold FE: Vue 3, TypeScript strict, Vite, Vue Router; Vitest/Prettier tooling
matches Identity. This first step only includes the top page and a link to the
Rust demo API. No application features, auth, admin or data migration yet.

```sh
cd foreign
npm ci
cp .env.example .env
npm run dev
```

Open **http://localhost:5174**. `VITE_API_BASE_URL` is the gateway origin, without
`/auth` or `/foreign`. The example uses port 8088 for local port forwarding;
use `http://localhost:8080` when running the gateway directly on its default port.
The demo link opens `GET /foreign/` on that gateway.

```sh
npm run build
npm run preview
# Or serve the build with the same Node static-server pattern as Identity:
PORT=8082 npm start
```

Directories are ready under `src/modules/foreign/{domain,application,infrastructure,presentation}`
and `src/shared`. Only the presentation top page has content in this scaffold.
The initial page is a placeholder, not the migrated mobile/tablet/laptop UI.

`npm run type-check` and `npm run format:check` are available. Vitest is configured
for future feature tests; none are added for this static scaffold.

Backend module: `backend/modules/foreign`. Migration plan:
[`docs/foreign-migration-plan.md`](../docs/foreign-migration-plan.md).

## Container và CD

Dockerfile build Vue rồi chạy Node static server trên cổng 8080, UID/GID 10001.
Không cần DB, app secret hoặc node_modules trong runtime image. Health: /healthz.

```sh
docker build -t foreign:local --build-arg VITE_API_BASE_URL=https://backend.nvnhan0810.com foreign/
docker run --rm -p 8082:8080 foreign:local
```

Các lệnh Docker trên chạy từ root monorepo. Workflow `.github/workflows/foreign.yml`
build/push image `ghcr.io/nvnhan0810/nvnhan0810.com/foreign:<7-char-SHA>` khi
`foreign/**` đổi trên main, rồi gọi deploy webhook với `repo=foreign`.
GitHub variable `FOREIGN_API_BASE_URL` override API origin lúc build;
`FOREIGN_DEPLOY_REPO` override mapping webhook nếu cần. Secret webhook dùng
`DEPLOY_HOOK_TOKEN` hoặc `DEPLOY_WEBHOOK_TOKEN` giống Identity.

K3s: repo `~/www/k3s`, path `apps/foreign.nvnhan0810.com`.
Frontend chỉ phục vụ UI; `/foreign/` API do gateway Rust cung cấp trên API host.
Bản này mới là trang đầu/demo. Sync cấu hình mới sẽ thay web cũ bằng scaffold,
đồng thời bỏ migration/queue cũ khỏi Kustomization; không xóa DB/media nguồn.
Chưa có migration nghiệp vụ Foreign hoặc cutover dữ liệu.

Port dev 5174 dùng chung quy ước Identity; dừng Identity dev khi chạy Foreign
trên cùng máy hoặc override `npm run dev -- --port 5175` nếu cần chạy cả hai.
