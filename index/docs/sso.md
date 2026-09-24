# Central SSO (index)

Google OAuth chạy **chỉ** trên `index` (`nvnhan0810.com` / `dev.nvnhan0810.com`).
App con (`wallets.*`, `flc.*`, `todo.*`) redirect tới IdP rồi đổi `code` lấy profile.

## Endpoints (index)

| Method | Path                  | Mô tả                                                      |
| ------ | --------------------- | ---------------------------------------------------------- |
| GET    | `/auth/sso/authorize` | `client_id`, `redirect_uri`, `response_type=code`, `state` |
| GET    | `/auth/google/login`  | Google                                                     |
| GET    | `/callback`           | Google callback + hoàn tất SSO nếu có `sso.intent`         |
| POST   | `/api/auth/sso/token` | Đổi code (`client_secret` = `SSO_SECRET`)                  |

Allowlist: `config/auth.php` → `valid_emails`.

## Quản lý clients (admin)

| Method | Path                          | Mô tả        |
| ------ | ----------------------------- | ------------ |
| GET    | `/admin/sso-clients`          | Danh sách    |
| GET    | `/admin/sso-clients/create`   | Form tạo     |
| POST   | `/admin/sso-clients`          | Tạo          |
| GET    | `/admin/sso-clients/{id}/edit`| Form sửa     |
| PUT    | `/admin/sso-clients/{id}`     | Cập nhật     |
| DELETE | `/admin/sso-clients/{id}`     | Xóa          |

Trong DB (`sso_clients`): `client_id`, `name`, `domain`, `redirect_uris`, `redirect_uri_patterns`, `enabled`.

**Secret** (`SSO_SECRET`) chỉ ở env — page admin hiện dạng ẩn + nút copy để setup app con.

## Env cần set

**Một secret chung** (index + wallets + flc + todo):

```env
SSO_SECRET=<random-long-string>
```

Các URL `SSO_WALLETS_URL` / `SSO_FLC_URL` / `SSO_TODO_URL` chỉ dùng khi **migrate seed** lần đầu (điền sẵn clients vào DB). Sau đó sửa qua Admin → SSO clients.

Optional fallback link Todo trên menu:

```env
TODO_APP_URL=https://todo.nvnhan0810.com
# hoặc SSO_TODO_URL nếu chưa set TODO_APP_URL
```

## Setup app con

Trên page admin SSO clients, copy `SSO_SECRET` rồi set trên từng app:

```env
APP_URL=https://todo.nvnhan0810.com
SSO_IDP_URL=https://nvnhan0810.com
SSO_SECRET=<same as index>
SSO_CLIENT_ID=todo
SSO_REDIRECT_URI="${APP_URL}/auth/sso/callback"
```

Wallets / FLC tương tự — `SSO_CLIENT_ID` phải khớp `client_id` trong DB.

## Mobile FLC

1. `GET /api/auth/sso/redirect?redirect_uri=flc://oauth-callback`
2. Browser → index → Google → `flc://oauth-callback?code&state`
3. App `POST /api/auth/sso/exchange` → Sanctum token

Client `flc-mobile` dùng `redirect_uri_patterns` (regex), không cần redirect URI cố định.
