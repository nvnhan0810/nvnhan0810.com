# Foreign

Scaffold FE: Vue 3, TypeScript strict, Vite, Vue Router; Vitest/Prettier tooling
matches Identity. Includes Identity login, a protected profile page and English dictionary lookup.
Business features and data migration are not implemented yet.

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
and `src/shared`. Auth is implemented in the application/infrastructure layers.
The initial page is a placeholder, not the migrated mobile/tablet/laptop UI.

`npm run type-check` and `npm run format:check` are available. Vitest covers PKCE and browser sessions.

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
Bản này có trang đầu/demo và login/profile Identity. Sync cấu hình mới sẽ thay web cũ bằng scaffold,
đồng thời bỏ migration/queue cũ khỏi Kustomization; không xóa DB/media nguồn.
Chưa có migration nghiệp vụ Foreign hoặc cutover dữ liệu.

Port dev 5174 dùng chung quy ước Identity; dừng Identity dev khi chạy Foreign
trên cùng máy hoặc override `npm run dev -- --port 5175` nếu cần chạy cả hai.

## Login/auth: đã làm

- Authorization Code + PKCE S256 trực tiếp Identity, scope `profile`; không giới hạn tài khoản root.
- Callback `/auth/callback`, state một lần có hạn 10 phút; xóa code/state khỏi URL trước request.
- Access token trong RAM, refresh bằng cookie HttpOnly, không lưu token trong browser storage.
- Restore khi reload, refresh đồng thời được gộp; Web Locks tuần tự rotation giữa tab khi browser hỗ trợ.
- Trang `/profile` lấy từ `/auth/me`, chờ restore và yêu cầu đăng nhập.
- Logout revoke phiên app (không logout Google/SSO toàn cục); xóa local cả khi mạng lỗi và báo chưa thu hồi được phiên server.
- Docker/CI hỗ trợ `VITE_SSO_CLIENT_ID`, GitHub variable `FOREIGN_SSO_CLIENT_ID`, mặc định `foreign-web`.
- Vitest kiểm tra PKCE và lifecycle session.

## Cần làm / chưa làm

Bạn tự tạo client trên Identity, **không seed DB Identity**:

| Trường       | Production                                     | Local                                 |
| ------------ | ---------------------------------------------- | ------------------------------------- |
| Client ID    | `foreign-web` hoặc ID bạn chọn                 | Khớp `.env`                           |
| Client type  | `public`                                       | `public`                              |
| Domain       | `https://foreign.nvnhan0810.com`               | `http://localhost:5174`               |
| Redirect URI | `https://foreign.nvnhan0810.com/auth/callback` | `http://localhost:5174/auth/callback` |
| Scopes       | `profile`                                      | `profile`                             |
| Enabled      | true                                           | true                                  |

Domain là exact origin, không path. Có thể dùng client riêng cho local. Không dùng client secret.
API origin production: `https://backend.nvnhan0810.com`; gateway tra CORS từ client đã đăng ký.
Nếu ID khác mặc định, đặt variable rồi build lại image. Env runtime K3s không đổi được cấu hình Vite.

Còn cần tạo client, deploy và smoke test browser thật: login/callback, reload, hết hạn/refresh,
logout, nhiều tab và cookie/CORS trên domain thật. Test mock không thay thế kiểm thử này.

Demo API còn public; lookup yêu cầu bearer token với scope `profile` và active grant. Chưa có scope nghiệp vụ `foreign:*` hoặc dữ liệu cần ownership.
Guard FE không thay thế authorization backend. Chưa triển khai schema/importer, nghiệp vụ,
UI cũ, mobile/extension handoff và cutover dữ liệu; xem migration plan.

### Dữ liệu test local và master data production

```sh
make -C backend migrate
make -C backend seed
```

`seed` đọc DB_* từ backend/.env, tạo public client Identity Admin (`identity`)
và Foreign (`foreign-web`) cho localhost:5174, không cần access token. Hai FE
chạy luân phiên trên port này. Không tạo user giả; đăng nhập Google qua Identity.
Client đã tồn tại giữ nguyên, không ghi đè metadata. Chỉ chạy seed trên DB dev/test.

Production dùng `make -C backend master-data` hoặc CLI
`console master-data`: chỉ tạo Identity Admin theo IDENTITY_CORS_ORIGIN và
IDENTITY_REDIRECT_URI; Foreign production vẫn tạo thủ công trong Identity.

## Lookup — chỉ tra cứu

`/` chỉ điều hướng: chờ restore session, chưa login → `/login`, đã login → `/lookup`.
Đăng nhập qua Identity xong luôn đến `/lookup`; route lookup/profile đều có guard.
Không hiển thị lookup trong lúc kiểm tra phiên. Logout trở về login.

UI dùng lại theme FLC và bố cục mobile cũ: header, bottom nav (chỉ các tính năng
đã triển khai), input có nút Clear, nút Look up, card kết quả, Pronounce,
UI/JSON chỉ đọc, nghĩa/ví dụ/đồng-trái nghĩa. Không lưu/sửa từ.
Tablet 768–1023px dùng form ngang và vùng đọc có max-width. Laptop từ 1024px có navigation trái, vùng search sticky và vùng kết quả riêng; từ 1280px tăng khoảng cách/chiều rộng vùng đọc. Mobile giữ form dọc/bottom nav.

`GET /foreign/dictionary?q=hello` yêu cầu Bearer token; gateway kiểm tra chữ ký,
expiry, scope `profile`, active grant và tài khoản qua Identity trong cùng process.
FE lấy token trong RAM, refresh và retry một lần khi API trả 401.

Ba provider theo source Laravel cũ (bản Go chỉ còn provider đầu):

1. `api.dictionaryapi.dev/api/v2/entries/en/{word}`
2. `freedictionaryapi.com/api/v1/entries/en/{word}`
3. `api.suvankar.cc/dictionaryapi/v1/definitions/en/{word}?compact=true`

Thử tuần tự khi không có từ hoặc provider lỗi. Nguồn lỗi được tạm bỏ qua 15 phút,
rate limit 1 giờ; cooldown trong RAM theo process, reset khi restart. Nếu có nguồn
không truy cập được và không tìm được ở nguồn còn lại, báo unavailable thay vì
kết luận chắc chắn không tồn tại từ. Timeout 3 giây/provider, tối đa 8 request
lookup đồng thời, 2 MiB/provider, không redirect. Không DB/cache nội dung từ điển.
Provider 2/3 được map theo contract source cũ; chưa xác minh live production.

FE tách `application/ports/dictionary.ts`, use cases `lookup-word.ts` và
`initialize-session.ts`, `infrastructure/dictionary-api.ts`,
`presentation/composables/use-lookup.ts`, components `LookupSearch` và
`DictionaryResult`. Page chỉ ghép UI; state/race/cancellation nằm trong composable,
validation nằm trong use case, HTTP/token retry nằm trong adapter.

Có tests use case, session routing, callback, component lookup/error/race,
provider DTO và fallback/cooldown. Đã kiểm tra Chromium với API/session fixture ở 360/390/430/768/820/1024/1280/1440px, không overflow ngang và các cột laptop không chồng nhau. Chưa đối chiếu screenshot với app cũ, chưa test live audio/provider,
DB/import, lưu/sửa từ, resolve biến thể, JSON editor, copy prompt và embed/WebView.
Không gọi AI.

Nút sáng/tối ở trang login và Profile; header laptop (từ 1024px) vẫn có nút theme/logout, mobile/tablet dùng các nút trong Profile; lưu `flc-theme` trong localStorage, lần đầu theo theme hệ điều hành.
