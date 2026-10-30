# Backend

Rust workspace, Axum API gateway. Binary `api-gateway` lắng nghe `0.0.0.0:8080`.

## Kiểm tra local trước push

Từ root repo chạy một lần cho mỗi clone:

```sh
bash .githooks/install
```

Hook kiểm tra snapshot commit sắp push khi backend thay đổi; không sửa working tree.
Chạy lần lượt format, Clippy (warnings là lỗi), test toàn workspace và build release.
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
docker run --rm -p 127.0.0.1:8080:8080 backend:local
curl -f http://127.0.0.1:8080/healthz
```

Workflow `.github/workflows/backend.yml` chỉ build/push image khi push main có thay
đổi backend hoặc workflow, và khi chạy thủ công. Không chạy format, lint, test trên
GitHub Actions. Image hỗ trợ linux/amd64 và linux/arm64; build ARM qua QEMU có thể
mất nhiều thời gian. `GITHUB_TOKEN` cần quyền packages:write.
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
| Environment / Secrets | Hiện chưa đọc biến môi trường, chưa kết nối database |
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

Lưu ý hiện trạng ứng dụng: CORS đang permissive. Rate limiter dùng peer IP của kết
nối (2 request/giây, burst 5); sau Ingress, nhiều client có thể dùng chung bucket
của proxy và nhận 429. Trước khi mở public, quyết định đặt rate limit ở ingress hoặc
thiết kế trusted-proxy IP extraction trong app. Không tin X-Forwarded-For tùy ý.
TraceLayer hiện chưa có tracing subscriber nên chưa xuất request logs đầy đủ.

Tham khảo: [Docker multi-platform Actions](https://docs.docker.com/build/ci/github-actions/multi-platform/),
[Argo CD automated sync](https://argo-cd.readthedocs.io/en/stable/user-guide/auto_sync/).
