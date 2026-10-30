# Identity Admin

Project Vue 3 + TypeScript + Vite độc lập trong workspace, tham khảo phong cách
portfolio (dark theme, emerald, module/application/infrastructure/presentation).
Đây là SPA quản trị riêng, không cần SSR/SEO và không dùng dữ liệu demo.
Chỉ system root nguyenvannhan0810@gmail.com được vào trang quản trị.
API backend vẫn là nơi quyết định quyền; kiểm tra trên UI không thay thế kiểm tra API.

## Chạy local

Yêu cầu Node.js 24 và backend Identity đã migrate, có Google client/keypair.

```sh
cd identity
cp .env.example .env
npm ci
npm run dev
```

Mở **http://127.0.0.1:5174**. Không đổi thành localhost nếu chưa đăng ký thêm callback;
Identity so khớp callback chính xác và HTTP callback web local dùng IP loopback.

```dotenv
VITE_API_BASE_URL=http://localhost:8080
VITE_SSO_CLIENT_ID=identity
```

Cấu hình này là public, được nhúng vào browser bundle lúc build. Không đặt
client secret/Google secret/private key vào biến VITE_*. Không cần confidential
client secret vì portal dùng public client + Authorization Code + PKCE S256.

Backend local cần:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8080
IDENTITY_CORS_ORIGIN=http://127.0.0.1:5174
```

Google Console đăng ký callback của **backend**:
http://localhost:8080/auth/google/callback. Callback của **portal** là:
http://127.0.0.1:5174/auth/callback. Hai callback này khác nhau.

## Đăng ký client trước khi dùng lần đầu

Backend có command seed để tạo public client identity trước lần
đăng nhập đầu tiên. Job k3s backend tự chạy migrate up rồi seed.
Để chạy thủ công:

```sh
cd backend
make migrate
make seed
```

Seeder lấy callback từ `IDENTITY_REDIRECT_URI`; production đặt
`https://identity.nvnhan0810.com/auth/callback`, local đặt
`http://localhost:5174/auth/callback` nếu mở frontend bằng localhost.


Nếu client đã có, command không sửa hoặc in lại dữ liệu. Muốn thêm callback khác,
sửa client qua API quản trị. CORS backend phải cho phép origin của portal.
Workflow .github/workflows/identity.yml build image từ identity/ và gọi deploy
webhook repo=identity. Manifests nằm trong repo k3s tại apps/identity.nvnhan0810.com.

## Chức năng

- Google SSO, state một lần, PKCE S256 và kiểm tra profile qua GET /auth/me.
- Tổng quan từ dữ liệu thật của tối đa 25 clients/users đầu tiên; không giả lập tổng số.
- Clients: tạo, sửa, callback/scopes, bật/tắt, rotate confidential secret.
- Secret mới chỉ hiển thị một lần trong modal, không lưu browser storage.
- Users: tìm kiếm, lọc, phân trang cursor, xem chi tiết, khóa/mở, revoke phiên.
- Khóa/revoke root bị vô hiệu hóa trên UI và tiếp tục được backend bảo vệ.
- Xác nhận thao tác ảnh hưởng truy cập; xử lý lỗi API, version conflict và retry.
- Tìm kiếm/lọc áp dụng trên danh sách đã tải; dùng Tải thêm để đọc trang tiếp theo.
- Không có xóa client/user, sửa email hoặc phân vai trò vì backend chưa hỗ trợ.

## Token và đăng xuất

Access/refresh token chỉ nằm trong memory của tab. Reload tab sẽ cần bắt đầu SSO
lại; central login của Identity có thể giúp bỏ qua Google login lại.
Session storage chỉ giữ state/verifier/callback tạm tối đa 10 phút, bị consume
ngay khi callback. URL callback được xóa code/state trước khi gọi API tiếp theo.
Không lưu bearer token trong localStorage/sessionStorage/cookie.

Refresh được serialize để tránh dùng lại token cũ do nhiều API request cùng lúc.
Retry API tối đa một lần khi gặp 401. Logout xóa memory và revoke refresh grant;
không xóa central browser login/Google login. Nếu revoke lỗi do mất mạng, UI báo
rõ; trang admin vẫn xóa token tại máy. Các API khác có thể chấp nhận JWT cũ tới
expiry như policy của backend.

## Build, test và container

```sh
npm run test
npm run build
npm run test:server
npm start
```

Server production dùng Node.js 24, cổng mặc định 8080, hỗ trợ SPA fallback cho
/clients, /users, /auth/callback; health GET /healthz. Không serve source/.env.
Có thể đổi HOST và PORT lúc chạy. Nên kết thúc TLS tại ingress/reverse proxy.

Từ repo root:

```sh
docker build --build-arg VITE_API_BASE_URL=https://backend.nvnhan0810.com \
  --build-arg VITE_SSO_CLIENT_ID=identity -t identity-admin:local identity/
docker run --rm -p 127.0.0.1:8081:8080 identity-admin:local
```

VITE_* là cấu hình build-time; đổi env ở runtime container không thay bundle đã build.
Backend root/credentials và dữ liệu production chưa được gọi trong quá trình phát triển.

## Cấu trúc

```text
src/app/                           App shell, router setup, services, styles
src/modules/identity/domain/       DTO và định danh root
src/modules/identity/application/  SSO session, PKCE, form validation
src/modules/identity/infrastructure/ API adapter, transport, error mapping
src/modules/identity/presentation/ Pages và components
src/shared/                        Notifications, confirmations
server/                            Static production server
```

Dữ liệu client/user, lỗi và thay đổi trạng thái đều từ Identity API. Test dùng
HTTP mocks, không kết nối DB hoặc endpoint production. Production cần API hoạt động,
CORS đúng origin và client/callback đã đăng ký.

### Login local trả invalid_request

Frontend gửi callback bằng origin đang mở trong trình duyệt. Nếu forward VM và
mở `http://localhost:5174`, phải đăng ký chính xác
`http://localhost:5174/auth/callback` trong redirect_uris của client `identity`.
`localhost`, `127.0.0.1` và domain production là các origin khác nhau.
Trong backend local, đặt `IDENTITY_CORS_ORIGIN=http://localhost:5174` trước khi
seed DB mới và đặt `IDENTITY_CORS_ORIGIN=http://localhost:5174`.
Seed không sửa client đã tồn tại; với DB cũ cần cập nhật callback đã đăng ký.
Restart backend sau khi đổi env. Lỗi authorize được ghi ở terminal chạy backend
với reason cụ thể; đây là navigation trình duyệt nên không qua catch của frontend.
