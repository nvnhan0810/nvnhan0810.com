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

## Env cần set

**Một secret chung** (index + wallets + flc + todo):

```env
SSO_SECRET=<random-long-string>
SSO_WALLETS_URL=https://wallets.nvnhan0810.com
SSO_FLC_URL=https://foreign.nvnhan0810.com
SSO_TODO_URL=https://todo.nvnhan0810.com
# Optional: admin Header → Matrix (defaults to SSO_TODO_URL)
TODO_APP_URL=https://todo.nvnhan0810.com
```

Dev:

```env
SSO_WALLETS_URL=https://wallets-dev.nvnhan0810.com
SSO_FLC_URL=https://foreign-dev.nvnhan0810.com
SSO_TODO_URL=https://todo-dev.nvnhan0810.com
TODO_APP_URL=https://todo-dev.nvnhan0810.com
```

Không cần `SSO_CLIENT_ID` / `SSO_REDIRECT_URI` / `SSO_CLIENT_*_REDIRECT_URIS` trên index
(whitelist nằm trong `config/sso.php`).

| App        | `client_id`  | `redirect_uri`                          |
| ---------- | ------------ | --------------------------------------- |
| wallets    | `wallets`    | `{SSO_WALLETS_URL}/auth/sso/callback`   |
| todo       | `todo`       | `{SSO_TODO_URL}/auth/sso/callback`      |
| flc web    | `flc-web`    | `{SSO_FLC_URL}/auth/sso/callback`       |
| flc admin  | `flc-admin`  | `{SSO_FLC_URL}/admin/auth/sso/callback` |
| flc mobile | `flc-mobile` | `flc://oauth-callback` (pattern)        |

Todo:

```env
APP_URL=https://todo.nvnhan0810.com
SSO_IDP_URL=https://nvnhan0810.com
SSO_SECRET=<same as index>
SSO_CLIENT_ID=todo
SSO_REDIRECT_URI="${APP_URL}/auth/sso/callback"
```

Wallets:

```env
APP_URL=https://wallets.nvnhan0810.com
SSO_IDP_URL=https://nvnhan0810.com
SSO_SECRET=<same as index>
```

FLC:

```env
APP_URL=https://foreign.nvnhan0810.com
SSO_IDP_URL=https://nvnhan0810.com
SSO_SECRET=<same as index>
```

## Mobile FLC

1. `GET /api/auth/sso/redirect?redirect_uri=flc://oauth-callback`
2. Browser → index → Google → `flc://oauth-callback?code&state`
3. App `POST /api/auth/sso/exchange` → Sanctum token
