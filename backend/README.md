# Backend

Rust workspace, Axum API gateway. Binary `api-gateway` lắng nghe `0.0.0.0:8080`.

## Kiểm tra local trước push

Từ root repo chạy một lần cho mỗi clone:

```sh
bash .githooks/install
```

Hook kiểm tra snapshot commit sắp push khi `backend/` hoặc `.githooks/check-backend`
thay đổi so với remote tip; không sửa working tree. Thay đổi hook portfolio,
`install` hoặc `pre-push` không tự kích hoạt kiểm tra backend.
Chạy lần lượt format, Clippy (warnings là lỗi) và test toàn workspace.
Hook không build release; GitHub Actions build image và báo lỗi nếu build thất bại.
Clippy và test vẫn cần biên dịch Rust nên vẫn sử dụng RAM.
Lỗi bất kỳ bước nào sẽ chặn push. Cần Rust 1.99.1 với rustfmt/clippy và mạng để tải
crate chưa có trong cache. Portfolio vẫn có hook riêng. Commit không chạy check.
Git vẫn cho phép bỏ qua hook bằng `--no-verify`.

Chạy thủ công từ thư mục backend:

```sh
cargo fmt --all -- --check
cargo clippy --locked --workspace --all-targets -- -D warnings
cargo test --locked --workspace
cargo build --locked --release --package api-gateway
```

## Docker và GitHub Actions

```sh
docker build -t backend:local backend/
docker run --rm --env-file backend/.env \
  -v /tmp/identity-keys:/run/secrets/identity:ro \
  -p 127.0.0.1:8080:8080 backend:local
curl -f http://127.0.0.1:8080/healthz
```

Workflow `.github/workflows/backend.yml` chỉ build/push image khi push main có thay
đổi backend hoặc workflow, và khi chạy thủ công. Không chạy format, lint, test trên
GitHub Actions. Image chỉ build `linux/amd64`, đúng kiến trúc node k3s hiện tại;
không chạy giả lập QEMU hay build ARM64. `GITHUB_TOKEN` cần quyền packages:write.
Dockerfile dùng cargo-chef để tách layer dependencies khỏi source, kết hợp cache
GitHub Actions `scope=backend,mode=max`. Khi chỉ đổi source, dependencies đã compile
có thể được tái sử dụng. Lần đầu hoặc khi đổi dependencies/toolchain vẫn build lâu.
Sau khi build thành công, workflow gọi deploy webhook hiện có; không kết nối cluster trực tiếp.

Image riêng: `ghcr.io/nvnhan0810/nvnhan0810.com/backend:<7-char-SHA>`.
Tag là SHA commit ngắn đúng 7 ký tự, không prefix, không latest. Ví dụ `a1b2c3d`.
Image được build và payload deploy dùng cùng tag.

## Thông tin chuyển sang repo Argo CD / k3s riêng

| Cấu hình | Giá trị |
| --- | --- |
| Image | `ghcr.io/nvnhan0810/nvnhan0810.com/backend` |
| Tag | SHA commit ngắn 7 ký tự, lấy từ Actions summary |
| Container port | `8080/TCP`, đặt tên `http` |
| Service | ClusterIP, port `80`, targetPort `http` |
| Startup/readiness/liveness | HTTP GET `/healthz`, port `http`, HTTP 200 JSON `{"message":"ok"}` |
| User/group | `10001:10001` |
| Storage | Không cần PVC hay writable filesystem |
| Environment / Secrets | PostgreSQL, Google OAuth và RSA keys; xem .env.example |
| Shutdown | Nhận SIGTERM, graceful shutdown; đặt terminationGracePeriodSeconds: 30 |
| Resources ban đầu | requests: cpu 100m, memory 64Mi; limits: cpu 500m, memory 256Mi; điều chỉnh theo tải |

Deployment nên đặt `runAsNonRoot: true`, `runAsUser: 10001`, `runAsGroup: 10001`,
`readOnlyRootFilesystem: true`, `allowPrivilegeEscalation: false`,
`capabilities.drop: [ALL]`, `seccompProfile.type: RuntimeDefault`.

Probe gợi ý: startup periodSeconds 2 / failureThreshold 30; readiness periodSeconds 5;
liveness periodSeconds 10 / failureThreshold 3; timeoutSeconds 2 cho cả ba.
`/healthz` không qua rate limiter và hiện chỉ kiểm tra tiến trình phục vụ HTTP.

Trong repo GitOps riêng:

1. Tạo Namespace, Deployment, Service và Ingress (nếu public). Chọn namespace/domain
   của bạn; ingressClassName `traefik` nếu dùng Traefik của k3s. Route `/` không rewrite
   về Service port 80. TLS secret/cert-manager issuer do cluster của bạn quản lý.
2. Nếu GHCR package private, tạo imagePullSecret kiểu docker-registry trong cùng
   namespace bằng GitHub username và token có `read:packages`; tham chiếu trong
   Deployment. Không commit token vào Git. Package public không cần pull secret.
3. Tạo Argo CD Application trỏ `repoURL` tới repo GitOps riêng, `targetRevision` tới
   nhánh deploy, `path` tới thư mục manifest backend; destination server là
   `https://kubernetes.default.svc`, namespace đúng bước 1. Cấu hình repo credential
   cho Argo nếu repo private. Có thể bật automated sync/selfHeal/prune và CreateNamespace.
4. Cấu hình mapping deploy API: `repo=backend`, `tag=production`, image như bảng trên.
   API cần cập nhật image tag trong repo GitOps để Argo sync. Có thể đổi mapping bằng
   GitHub Actions variable `BACKEND_DEPLOY_REPO` (mặc định `backend`). Workflow gọi
   `POST https://deploy.nvnhan0810.com/deploy` với payload `repo`, `tag`, `image`, `image_tag`.
   Đặt secret `DEPLOY_HOOK_TOKEN` hoặc fallback `DEPLOY_WEBHOOK_TOKEN`; thiếu token
   sẽ fail workflow. Argo auto-sync dựa trên thay đổi manifest trong Git.
   Rollback bằng revert commit image hoặc chạy workflow thủ công với `deploy_only=true`
   và `image_tag` của image có sẵn (7 ký tự hex viết thường). Bỏ trống dùng SHA hiện tại.
   Khi `deploy_only=false`, để trống `image_tag` để build/deploy cùng commit.
5. Kiểm tra rollout, pod logs, probe và gọi `/healthz`, `/`, `/auth/`, `/order/`.

Lưu ý hiện trạng ứng dụng: CORS dùng allowlist origins từ cấu hình. Rate limiter dùng peer IP của kết
nối (2 request/giây, burst 5); sau Ingress, nhiều client có thể dùng chung bucket
của proxy và nhận 429. Trước khi mở public, quyết định đặt rate limit ở ingress hoặc
thiết kế trusted-proxy IP extraction trong app. Không tin X-Forwarded-For tùy ý.
TraceLayer hiện chưa có tracing subscriber nên chưa xuất request logs đầy đủ.

Tham khảo: [Docker multi-platform Actions](https://docs.docker.com/build/ci/github-actions/multi-platform/),
[Argo CD automated sync](https://argo-cd.readthedocs.io/en/stable/user-guide/auto_sync/).

## Identity SSO OAuth2 — chạy với DB mới

Module `identity` cung cấp Google login, SSO giữa các app nội bộ,
Authorization Code + PKCE S256, JWT RS256 và refresh token rotation.
Không phát hành OIDC ID token. App tự chọn lưu token trong backend session,
cookie hoặc secure storage; API không dùng cookie SSO làm bearer credential.

### 1. Tạo database và cấu hình

Tự tạo PostgreSQL database rỗng và user có quyền tạo bảng. Không dùng DB Laravel.
Migration up không drop/reset DB. Copy `.env.example` thành `.env` và điền cấu hình.
App tự đọc `.env` tại thư mục chạy, hoặc `backend/.env` nếu chạy từ repo root.
Biến môi trường của process/k3s được ưu tiên hơn file:

```sh
cd backend
cp .env.example .env
# Sửa .env rồi chạy app; không cần source/export thủ công.
```

Google OAuth client loại Web application cần đăng ký callback chính xác:

```text
https://api.nvnhan0810.com/auth/google/callback
```

Mọi tài khoản Google có email đã xác minh đều được đăng nhập nếu user chưa bị khóa.
System root cố định là nguyenvannhan0810@gmail.com và hiện là admin duy nhất.
Không dùng email allowlist hoặc danh sách admin qua env; phân quyền chi tiết làm sau.
Scopes được quản lý trực tiếp trên từng client trong DB qua API admin.
APP_URL là URL chung của backend, ví dụ https://api.nvnhan0810.com.
Identity tự dùng APP_URL cho cả JWT iss và aud; không có audiences trên client
hoặc tham số resource trong authorize. Mỗi module kiểm tra scope của mình.
Scope phải là token OAuth hợp lệ, danh sách không rỗng hoặc trùng.
Scope identity.clients.manage hiện chỉ được cấp cho system root.

Tạo RSA keypair cho production ở nơi riêng, không dùng key trong test fixtures:

```sh
umask 077
mkdir -p /tmp/identity-keys
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out /tmp/identity-keys/private.pem
openssl pkey -in /tmp/identity-keys/private.pem -pubout -out /tmp/identity-keys/public.pem
```

Đặt `IDENTITY_PRIVATE_KEY_PATH` và `IDENTITY_PUBLIC_KEY_PATH` đúng đường dẫn.
Trong k3s, mount read-only từ Secret. Không đưa key/credential vào image/Git.

### 2. Chạy migration và web server

```sh
cargo run --locked --package api-gateway --bin migrate -- up
cargo run --locked --package api-gateway
```

`migrate up` chỉ cần DB_HOST, DB_PORT, DB_NAME, DB_USERNAME và DB_PASSWORD, không cần cấu hình Google/key.
Migration up/down được nhúng trong binary; production chạy `migrate up`
bằng Job trước rollout. Server không tự migrate khi start.
Migration chỉ tạo schema, không seed client hoặc user. Ví dụ authorize bên dưới
giả định client đã có trong DB; công cụ khởi tạo dữ liệu sẽ bổ sung khi cần.
Không có tài khoản/password hoặc admin endpoint bỏ xác thực.

### Rollback migration trong dev

Mỗi migration có hai file: `<version>_<name>.up.sql` và
`<version>_<name>.down.sql`. Up chạy theo thứ tự tăng dần, down chạy ngược.
Rollback mặc định lùi một migration, không phải cả batch như Laravel.

```sh
# Lùi migration ban đầu: xóa schema Identity và dữ liệu:
cargo run --locked --package api-gateway --bin migrate -- down

# Giữ schema tại version chỉ định; undo các version lớn hơn:
cargo run --locked --package api-gateway --bin migrate -- down 20261030000001

# Undo toàn bộ migration Identity: xóa các bảng Identity và dữ liệu trong đó:
cargo run --locked --package api-gateway --bin migrate -- down 0

# Chạy lại up:
cargo run --locked --package api-gateway --bin migrate -- up
```

Các lệnh migration chỉ cần DB_HOST, DB_PORT, DB_NAME, DB_USERNAME và DB_PASSWORD. Kiểm tra DB đích trước rollback;
down schema xóa dữ liệu, up lại chỉ tạo bảng rỗng. Hiện chỉ có một migration
ban đầu (một cặp up/down), đã gộp schema cuối cùng vì chưa deploy production.
DB dev đã chạy bộ migration cũ cần tạo DB dev rỗng mới; không sửa thủ công bảng
_sqlx_migrations để bỏ qua checksum. Sau khi deploy, tạo migration mới cho thay đổi.

### Cấu hình dùng chung khi khởi động

`apps/api-gateway/src/config/` là nơi duy nhất đọc env và .env.
ConfigSource giữ snapshot bất biến; AppConfig chuyển nó thành cấu hình có kiểu,
kiểm tra APP_URL, CORS origins và các cấu hình bắt buộc.
IdentityConfig chỉ chứa dữ liệu, không tự đọc env.

```text
.env + process env
  -> ConfigSource (load một lần)
  -> AppConfig
  -> IdentityState::new(config, database.connect_options(), key_paths)
  -> các handler/provider sử dụng lại Arc<IdentityConfig>
```

Không gọi env::var trong repository, entity hoặc provider. Khi thêm module mới,
thêm phần cấu hình vào AppConfig và truyền xuống từ composition root.
Thay env cần restart app; không tự reload giữa các request.
Migration command chỉ đọc phần database từ cùng startup snapshot nên không yêu
cầu Google credentials hoặc signing keys.

### 3. Đăng nhập và lấy token quản trị

Sinh PKCE verifier và challenge (giữ verifier tại app, không gửi ở authorize):

```sh
python3 - <<'PY'
import base64, hashlib, secrets
verifier = secrets.token_urlsafe(32)
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b"=").decode()
print("verifier =", verifier)
print("challenge =", challenge)
print("state =", secrets.token_urlsafe(32))
PY
```

Mở browser tới URL sau, URL-encode các giá trị:

```text
https://api.nvnhan0810.com/auth/sso/authorize
?response_type=code
&client_id=identity-admin
&redirect_uri=http%3A%2F%2F127.0.0.1%3A8250%2Fcallback
&scope=identity.clients.manage
&state=<random-state>
&code_challenge=<challenge>
&code_challenge_method=S256
```

URL phải nằm trên một dòng. Sau Google login, browser quay về callback với code
và state. App phải kiểm tra state khớp giao dịch ban đầu và dùng một lần.
Trong kiểm thử thủ công có thể lấy code từ URL ngay cả khi chưa có callback server.

```sh
curl -sS https://api.nvnhan0810.com/auth/sso/token   --data-urlencode grant_type=authorization_code   --data-urlencode client_id=identity-admin   --data-urlencode code=YOUR_CODE   --data-urlencode redirect_uri=http://127.0.0.1:8250/callback   --data-urlencode code_verifier=YOUR_VERIFIER
```

Response gồm access_token, token_type=Bearer, expires_in=900, refresh_token, scope.
Code hết hạn sau 120 giây và chỉ đổi một lần. Refresh grant có hạn tuyệt đối 30 ngày.

### 4. API quản lý client

Tất cả yêu cầu bearer token có audience bằng APP_URL, scope
`identity.clients.manage`, user admin còn enabled và grant chưa bị revoke.

| Method | Path | Body |
| --- | --- | --- |
| POST | /auth/admin/sso-clients | Client mới |
| GET | /auth/admin/sso-clients?limit=20&after=UUID | List phân trang |
| GET | /auth/admin/sso-clients/{id} | |
| PATCH | /auth/admin/sso-clients/{id} | Metadata đầy đủ + version |
| POST | /auth/admin/sso-clients/{id}/rotate-secret | {"version":1} |

Ví dụ tạo client app có backend:

```sh
curl -sS https://api.nvnhan0810.com/auth/admin/sso-clients   -H "Authorization: Bearer YOUR_ADMIN_ACCESS_TOKEN"   -H 'Content-Type: application/json'   -d '{
    "client_id":"wallets-web",
    "name":"Wallets",
    "domain":"https://wallets.nvnhan0810.com",
    "client_type":"confidential",
    "redirect_uris":["https://wallets.nvnhan0810.com/auth/sso/callback"],
    "scopes":["profile","wallets:read","wallets:write"],
    "enabled":true
  }'
```

Response có `client` và `client_secret` (chỉ lúc tạo/rotate). Lưu secret an toàn
ở backend app. GET không trả hash/secret. Public client không có secret.
PATCH nhận name, domain, redirect_uris, scopes, enabled, version;
client_id và client_type bất biến. Version cũ trả 409.
Dùng enabled=false để ngừng client, không hỗ trợ xóa cứng.

Callback khớp chính xác, không wildcard/regex. HTTPS cho web, HTTP chỉ IP loopback.
Native app có thể dùng reverse-domain scheme như `com.example.app:/callback`.
Mỗi web/mobile consumer nên có client riêng.

### 5. Tích hợp app A/B và refresh

App gọi authorize như bước 3 với client/callback/scope của mình.
Backend app confidential đổi code bằng `Authorization: Basic`; OAuth Basic phải
form-url-encode từng credential trước Base64. Client ID/secret sinh bởi hệ thống
hiện chỉ dùng ký tự URL-safe nên có thể dùng `curl -u CLIENT_ID:CLIENT_SECRET`.
Public client chỉ gửi client_id và verifier, không nhúng secret vào mobile/SPA.

Tất cả module cùng backend dùng aud=APP_URL. Middleware kiểm tra RS256, iss,
aud, expiry, kid và typ=at+jwt; từng endpoint kiểm tra scope và quyền dữ liệu.
Một token có thể dùng cho /wallets, /todo và /auth/me nếu chứa các scope tương ứng.
GET /auth/me yêu cầu scope profile, không cần token/audience riêng.

```sh
curl -sS https://api.nvnhan0810.com/auth/me   -H "Authorization: Bearer YOUR_PROFILE_ACCESS_TOKEN"

curl -sS https://api.nvnhan0810.com/auth/sso/token   -u CLIENT_ID:CLIENT_SECRET   --data-urlencode grant_type=refresh_token   --data-urlencode refresh_token=YOUR_REFRESH_TOKEN
```

Sau refresh phải thay refresh token cũ bằng token mới. Chỉ một refresh tại một
thời điểm cho mỗi family. Dùng lại token cũ (kể cả do retry/concurrency) revoke
toàn family; response refresh bị mất có thể cần login lại.
Không mở rộng scope khi refresh; audience luôn là APP_URL.

B dùng authorize riêng trên cùng browser: cookie HttpOnly host-only tại Identity
giúp bỏ qua Google login; B vẫn nhận token riêng. Cookie trung tâm không chia sẻ
cho A/B, không thay thế storage/token authentication của các app.

`IDENTITY_CORS_ORIGINS` cho phép cấu hình exact browser origins, phân tách dấu phẩy.
Mặc định không cho cross-origin AJAX; không bật CORS credentials.
Mobile/server-to-server không bị browser CORS giới hạn.

### 6. Revoke, logout và giới hạn hiện tại

```sh
curl -sS https://api.nvnhan0810.com/auth/sso/revoke   -u CLIENT_ID:CLIENT_SECRET   --data-urlencode token=YOUR_REFRESH_TOKEN   --data-urlencode token_type_hint=refresh_token
```

Public client gửi client_id trong body thay Basic. Revoke thu hồi grant của đúng
client; token không tồn tại trả 200. Chưa hỗ trợ revoke access token trực tiếp.

POST /auth/logout xóa browser login trung tâm, cần cookie và Origin đúng issuer.
Không có GET logout. Token của các app đã cấp không tự bị xóa khi central logout.
JWT tài nguyên còn hiệu lực tối đa 15 phút sau revoke/disable; resource server
muốn thu hồi tức thì cần bổ sung online check. Admin API có kiểm tra active grant.

Bản đầu dùng một signing key hiện hành; chưa có JWKS/discovery hoặc key overlap
rotation tự động. Resource servers nhận public key qua cấu hình. Khi đổi key,
cần phối hợp các resource server; không dùng key test fixtures.

App production bắt buộc APP_ENV=production và APP_URL HTTPS để cookie Secure hoạt động.
Google login được test bằng fake provider trong integration test, chưa gọi Google
thật với credential production.

### 7. Tests và cấu trúc

- Domain: mỗi entity trong domain/entities, mỗi value object trong domain/value_objects;
  errors và policies cũng có thư mục riêng. Aggregate client, redirect/client ID value objects, authorization policy,
  refresh grant invariants.
- Application: handlers điều phối use case qua repository, transaction, Google
  và crypto ports. Mỗi repository trait nằm trong application/ports/repositories;
  Google, crypto và transaction ports cũng có file riêng. Command và query handlers
  gom trong application/handlers.
- Infrastructure: PostgreSQL transactions, Google verification và JWT crypto.
- Presentation: routes và HTTP handlers riêng trong http/handlers.

```sh
cargo test --locked --workspace
# DB này phải là DB test riêng; migration sẽ tạo bảng và test sẽ thêm dữ liệu:
IDENTITY_TEST_DATABASE_URL=postgres://USER:PASSWORD@localhost/identity_test   cargo test --locked --package identity --test oauth_flow -- --ignored
```

Integration test không đọc các biến DB_* của app; mặc định ignored để push check không
phụ thuộc DB. Test gồm SSO A/B, exact callback, browser binding, PKCE, code replay,
concurrent exchange/refresh, secret rotation, token audience/expiry, admin quyền
và revoke. Không có lệnh drop DB.

Expired attempts/codes/browser logins có thể xóa định kỳ theo expires_at.
Giữ consumed refresh tokens tới khi grant hết hạn để phát hiện replay.


## System root và quản lý user

System root được xác định từ email Google đã xác minh, cố định trong
domain/policies/system_root.rs: nguyenvannhan0810@gmail.com.
Không tạo user giả trước Google login, không cho sửa email/Google subject qua API.

Access token có claim is_system_root để các module/resource server áp dụng quyền
root sau khi xác minh JWT. Flag này không bỏ qua chữ ký, expiry, audience hoặc
client policy. Các module nghiệp vụ mới cần dùng principal này khi kiểm tra quyền.
Tài khoản root bỏ qua yêu cầu scope quản trị tại API Identity, nhưng vẫn cần token
audience bằng APP_URL và grant/client còn hợp lệ.

Các API sau chỉ dành cho system root:

| Method | Path | Chức năng |
| --- | --- | --- |
| GET | /auth/admin/users?limit=20&after=UUID | Danh sách user đã Google login |
| GET | /auth/admin/users/{id} | Chi tiết user |
| PATCH | /auth/admin/users/{id} | Body {"enabled":false} hoặc {"enabled":true} |
| POST | /auth/admin/users/{id}/revoke | Thu hồi toàn bộ refresh grants và central logins |

Không cho khóa root hoặc revoke root qua API quản lý user. Schema ban đầu
bảo vệ enabled của root bằng CHECK constraint.
User bị khóa không thể login/exchange/refresh; thao tác khóa cũng revoke grants
và browser logins. JWT đã cấp cho API bên ngoài vẫn có thể sống tới expiry như
policy hiện tại. Hiện chỉ root quản trị user/client; chưa triển khai role hay
phân quyền user chi tiết. Scopes client và kiểm tra trạng thái user vẫn được giữ.


### Chuyển sang audience cố định APP_URL

Thay IDENTITY_ISSUER trong env cũ bằng APP_URL. Cập nhật Google callback sang
<APP_URL>/auth/google/callback nếu đổi domain. APP_URL được chuẩn hóa bỏ dấu /
cuối, phải là origin không có path/query/fragment. Production bắt buộc HTTPS;
APP_ENV=local hoặc development cho phép HTTP khi chạy dev.

Schema ban đầu đã bỏ audiences của client và audience trên code/grant.
JWT luôn tự lấy aud từ APP_URL; DB chỉ lưu scopes cho việc phân quyền module.
Bộ migration được gộp trước production. Nếu DB dev đã chạy bản migration cũ,
hãy dùng DB dev rỗng mới rồi chạy make migrate; không áp dụng bộ đã gộp lên lịch sử cũ.


### Cấu hình PostgreSQL theo từng biến

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=identity
DB_USERNAME=identity
DB_PASSWORD=CHANGE_ME
```

App và cả lệnh migrate up/down dùng chung DatabaseConfig được parse lúc startup.
Không dùng DATABASE_URL cho runtime nữa. DB_PORT phải từ 1 tới 65535.
DB_PASSWORD phải có mặt nhưng có thể rỗng nếu PostgreSQL local cho phép.
Mật khẩu truyền thẳng cho driver, không tự URL-encode; không có chuỗi URL ghép
thủ công. Với file .env local, quote giá trị có khoảng trắng/# theo cú pháp dotenv;
secret.env của kubectl dùng giá trị nguyên văn, không thêm dấu quote shell.

IDENTITY_TEST_DATABASE_URL vẫn là biến riêng, chỉ dành cho integration test trên
DB test độc lập, không phải cấu hình kết nối của app production.


### HTTP khi chạy dev, HTTPS khi production

Local .env:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8080
```

Production/k3s:

```dotenv
APP_ENV=production
APP_URL=https://api.nvnhan0810.com
```

APP_ENV mặc định production; giá trị không hợp lệ bị từ chối. Không suy luận môi
trường từ debug/release build. Production từ chối APP_URL/CORS origin dùng HTTP.
TLS bên ngoài được kết thúc tại ingress/proxy; server vẫn phục vụ HTTP nội bộ.
Cookie local HTTP không có Secure và không dùng __Host- prefix. Khi dùng HTTPS,
cookie giữ Secure + __Host-; cả hai chế độ đều HttpOnly và SameSite=Lax.
Google Console cần đăng ký callback local chính xác:
http://localhost:8080/auth/google/callback (ngoài callback production).


### Các entrypoint riêng

Package api-gateway có default-run=api-gateway:

| Binary | Entrypoint | Trách nhiệm |
| --- | --- | --- |
| api-gateway | src/main.rs | HTTP server mặc định |
| migrate | src/bin/migrate.rs | Migration up/down; chỉ cần DB_* |

Cấu hình dùng chung qua library api_gateway::config, không đọc env lặp ở module.
make dev và cargo run -p api-gateway vẫn chạy web server như trước.
make migrate, make migrate-down TARGET=0 gọi binary migration riêng.

Image Docker chứa hai binary api-gateway và migrate, ENTRYPOINT mặc định vẫn là api-gateway.
K3s migration Job phải override command thành ["/usr/local/bin/migrate"] và args
thành ["up"], không chỉ đổi args của web ENTRYPOINT. Ví dụ Docker local:

```sh
docker run --rm --env-file backend/.env --entrypoint migrate backend:local up
```
