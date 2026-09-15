# Central SSO (index)

Google OAuth chạy **chỉ** trên `index`. App con redirect tới IdP rồi đổi `code` lấy profile.

## Endpoints (index)

| Method | Path | Mô tả |
|--------|------|--------|
| GET | `/auth/sso/authorize` | `client_id`, `redirect_uri`, `response_type=code`, `state` |
| GET | `/auth/google/login` | Google |
| GET | `/callback` | Google callback + hoàn tất SSO nếu có `sso.intent` |
| POST | `/api/auth/sso/token` | Đổi code (`client_secret` = `SSO_SECRET`) |

Allowlist: `config/auth.php` → `valid_emails`.

## Env cần set

**Một secret chung** (index + wallets + flc):

```env
SSO_SECRET=<random-long-string>
APP_URL=https://nvnhan0810.com   # index: whitelist redirect = APP_URL + /wallets|flc/...
```

Không cần `SSO_CLIENT_ID` / `SSO_REDIRECT_URI` / `SSO_CLIENT_*_REDIRECT_URIS`.

| App | `client_id` (auto) | `redirect_uri` (auto) |
|-----|--------------------|------------------------|
| wallets | `APP_PATH_PREFIX` → `wallets` | `{APP_URL}/wallets/auth/sso/callback` |
| flc web | `flc-web` (code) | `{APP_URL}/flc/auth/sso/callback` |
| flc admin | `flc-admin` | `{APP_URL}/flc/admin/auth/sso/callback` |
| flc mobile | `flc-mobile` | `flc://oauth-callback` (pattern) |

Wallets chỉ cần:

```env
SSO_IDP_URL=https://nvnhan0810.com   # optional; default = APP_URL
SSO_SECRET=<same as index>
APP_URL=https://nvnhan0810.com
APP_PATH_PREFIX=wallets
```

## Mobile FLC

1. `GET /flc/api/auth/sso/redirect?redirect_uri=flc://oauth-callback`
2. Browser → index → Google → `flc://oauth-callback?code&state`
3. App `POST /flc/api/auth/sso/exchange` → Sanctum token
