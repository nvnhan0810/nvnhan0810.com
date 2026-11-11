# Deploy theo phần source thay đổi

Chạy [deploy.sh](../deploy.sh) trên máy deployment, trong checkout của repo.
Script dùng upstream đã cấu hình cho branch hiện tại, ví dụ `main` → `origin/main`.
Có thể gọi script từ working directory bất kỳ; nó tự tìm thư mục gốc repo.

## Cách chạy

```sh
# Xem kế hoạch: fetch remote, nhưng chưa pull/build/restart.
./deploy.sh --dry-run

# Pull và deploy những app thay đổi.
./deploy.sh

# Khi supervisorctl cần quyền sudo.
./deploy.sh --sudo

# Deploy lần đầu hoặc cần build lại toàn bộ app đang được cấu hình.
./deploy.sh --all
```

Nếu gọi từ thư mục khác, dùng đường dẫn đầy đủ tới script.
`--all` vẫn thực hiện kiểm tra local changes và pull fast-forward.

Yêu cầu: Bash 4+, Git, `flock`, quyền fetch remote. Các app được chọn cần Cargo/npm
và môi trường build tương ứng; app cần restart cần quyền chạy `supervisorctl`.
Node phải đáp ứng `engines` của các FE. Nếu dùng `--sudo`, chỉ lệnh Supervisor chạy
qua sudo; Git/Cargo/npm vẫn chạy dưới user hiện tại.

Source và script phải được commit, checkout sạch trước khi deploy. Các file cấu
hình local như `.env` cần được Git ignore. Script dừng khi gặp thay đổi local,
branch detached, branch chưa có upstream hoặc lịch sử local ahead/diverged.

## App và service

| Source thay đổi | Build | Supervisor restart |
| --- | --- | --- |
| `backend/` | Release build → migrate up → master-data | Stop/start `nvnhan0810-backend`, `nvnhan0810-backend-queue` |
| `portfolio/` | `npm i --include=dev --no-save`, `npm run build` | `nvnhan0810-com` |
| `identity/` | Như portfolio | Không cần restart |
| `foreign/` | Như portfolio | Không cần restart |
| `wallets/` | Như portfolio | Không cần restart |
| `todos/` | Như portfolio | Không cần restart |
| `tools/` | Như portfolio | Không cần restart |

`index` và `ai-embedding-enrichment` đã được loại khỏi phạm vi deploy. Thay đổi ở
các đường dẫn khác vẫn được pull nhưng không kích hoạt build app.

Portfolio là app duy nhất được cấu hình restart SSR. Tên service được khai báo
trực tiếp trong `deploy.sh`; sửa tại đó nếu Supervisor trên server đổi tên.

Các npm devDependencies được cài để có công cụ build ngay cả khi môi trường đặt
`NODE_ENV=production`. `--no-save` giữ thao tác cài đặt khỏi lưu thay đổi dependency
vào manifest/lockfile. Backend dùng release build để phù hợp đường dẫn binary
production `target/release`.

## Flow

1. Khóa deploy bằng `flock`, tránh hai deploy chạy cùng checkout.
2. Kiểm tra checkout sạch và branch có upstream.
3. `git fetch --prune` remote, kiểm tra có thể fast-forward.
4. Chọn revision deploy thành công trước đó làm mốc so sánh. Lần đầu dùng HEAD
   trước khi pull; dùng `--all` nếu checkout mới đã ở commit cần deploy.
5. Lưu mốc cũ rồi `git pull --ff-only --no-rebase`.
6. So sánh tên file giữa mốc đã deploy và HEAD mới, chọn app cần build. Rename được
   xét như xóa/thêm để nhận ra cả thư mục nguồn cũ và mới.
7. Build backend nếu được chọn, rồi lần lượt cài/build từng FE được chọn.
8. Sau khi mọi build thành công, nếu backend thay đổi: dừng/drain queue rồi dừng
   API, chạy migrate up, chạy master-data, khởi động lại API và queue.
9. Restart `nvnhan0810-com` nếu portfolio thay đổi.
10. Sau khi restart thành công mới ghi nhận HEAD mới là revision deploy thành công.

Nếu build/restart lỗi, script trả exit code khác 0 và giữ mốc deploy cũ. Chạy lại
cùng lệnh sẽ thử lại các phần còn thay đổi kể cả khi lần trước đã pull xong.
Một lần `--all` chưa thành công cũng giữ kế hoạch build toàn bộ cho lần thử lại.

State và lock được lưu trong Git metadata tại `deploy-state/` (checkout thông
thường: `.git/deploy-state/`). Checkpoint tách theo branch. Thay đổi lịch sử Git
không còn chứa checkpoint sẽ khiến script dừng để xử lý history trước.

`--dry-run` cập nhật remote refs qua fetch và dùng lock, nhưng giữ nguyên checkout,
checkpoint và services. Nó in lệnh dự kiến cho commit remote vừa fetch.

Script build ngay tại working tree hiện tại. Đây chưa phải cơ chế release theo
thư mục version hoặc tự rollback các file build; khi lỗi cần sửa nguyên nhân rồi
chạy lại. Các build hoàn tất trước lỗi có thể đã cập nhật assets tại chỗ.

## Migration database

Mỗi khi backend được chọn deploy, script tự chạy các lệnh sau từ `backend`, sau
release build và sau khi các FE được chọn đã build xong:

```sh
supervisorctl stop nvnhan0810-backend-queue
supervisorctl stop nvnhan0810-backend
cargo run --release --locked -p console --bin migrate -- up
cargo run --release --locked -p console --bin console -- master-data
supervisorctl start nvnhan0810-backend nvnhan0810-backend-queue
```

Script kiểm tra trạng thái trước khi stop; service đã dừng được bỏ qua để có thể
thử lại sau lỗi. Với `--sudo`, các lệnh Supervisor ở trên chạy qua sudo.
Dừng/drain queue trước migration cho phép chuyển queue cũ sang `default` an toàn.
Master-data dùng cấu hình backend hiện tại để cập nhật dữ liệu nền tảng.

Nếu migrate hoặc master-data thất bại, backend/API và queue được giữ dừng, checkpoint
không được nâng, portfolio chưa được restart. Sửa lỗi rồi chạy lại deploy; script
sẽ thử lại cả migrate và master-data trước khi start services. Các migration đã
commit trước lỗi không tự rollback. Xem thêm [queue lifecycle](../backend/docs/queue-lifecycle.md).

## Kiểm tra script

Tests tạo bare remote và checkout tạm trên máy local, dùng Cargo/npm/Supervisor giả
để kiểm tra chọn app, pull, dry-run, local changes, history diverged và retry sau
build/restart thất bại. Các tests không gọi service production hoặc remote GitHub.

```sh
bash -n deploy.sh
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s tests -p test_deploy.py -v
```

## Deploy riêng từng app qua GitHub Actions

Mỗi app có script `deploy.sh` trong thư mục source và workflow tương ứng trong
`.github/workflows/`: `backend`, `foreign`, `identity`, `portfolio`, `todos`, `wallets`, `tools`.
Workflow chạy khi push lên `main` có thay đổi trong app hoặc workflow, và hỗ trợ
`workflow_dispatch` trên `main`.

Workflow gọi `https://deploy.nvnhan0810.com/deploy` với repo key
`nvnhan0810_com_<app>` (ví dụ `nvnhan0810_com_todos`), dùng secret
`DEPLOY_HOOK_TOKEN` hoặc fallback `DEPLOY_WEBHOOK_TOKEN`. Deploy server cần cấu hình
các repo key này để chạy script tương ứng trong checkout trên server.

Các script pull `origin main` rồi build app. Frontend cài dependencies bằng
`npm i --include=dev --no-save` trước khi build. Portfolio restart service
`nvnhan0810-com`; các frontend còn lại chỉ build assets. Backend chạy release build,
migrate và master-data trước khi restart API/queue. Các script riêng gọi Supervisor
qua `sudo -n supervisorctl -c /etc/supervisor/supervisord.conf`; user deploy cần quyền
NOPASSWD cho lệnh tương ứng. Git/Cargo/npm chạy dưới user deploy.

Tools dùng `npm ci --include=dev` trong script riêng và build CI trước khi gọi webhook.
Cần đăng ký key `nvnhan0810_com_tools` trên deploy service để chạy `tools/deploy.sh`.
Nginx cho tools dùng cấu hình `tools/deploy/nginx.conf`, phục vụ directory index
(`/agent/` → `agent/index.html`) và trả 404 khi asset không tồn tại. Xem `tools/README.md`.
