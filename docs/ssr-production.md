# Inertia SSR – Production (VPS)

Hướng dẫn chạy Server-Side Rendering cho trang **public** (SEO). Trang `admin/*` không dùng SSR.

## Yêu cầu trên VPS

- **Node.js 20+** qua **fnm** (không cần cài apt `nodejs`)
- **Supervisor** (giữ SSR server chạy nền)
- **Không cần `node_modules`** trên VPS — SSR bundle đã bundle sẵn deps (`vite.config.js` → `ssr.noExternal: true`)

## Biến môi trường (.env production)

```env
INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=http://127.0.0.1:13714
```

SSR server chỉ listen localhost; không cần mở port 13714 ra internet.

## Build assets (local hoặc CI)

Script `npm run build` đã build cả client và SSR bundle:

```bash
npm ci
npm run build
```

Output SSR (mặc định):

- `bootstrap/ssr/ssr.js` hoặc `bootstrap/ssr/ssr.mjs`

Deploy script (`deploy.sh`) rsync cả thư mục `bootstrap/ssr/` lên VPS.

## Supervisor (fnm)

**Không dùng** `php artisan inertia:start-ssr` trong supervisor — lệnh đó gọi `node` trong PATH, mà `www-data` thường không có fnm.

**Không dùng** `user=www-data` nếu fnm cài trong `/root` — `www-data` không đọc được `/root/.local/share/fnm/...`.

Chạy **trực tiếp** file bundle bằng Node từ fnm, user `root`:

```bash
# Xem version đang dùng (ví dụ v24.14.0)
fnm current
ls /root/.local/share/fnm/node-versions/
```

Tạo `/etc/supervisor/conf.d/nvnhan0810-ssr.conf` (đổi path version/path app cho đúng):

```ini
[program:nvnhan0810-ssr]
process_name=%(program_name)s
command=/root/.local/share/fnm/node-versions/v24.14.0/installation/bin/node /opt/apps/nvnhan0810.com/bootstrap/ssr/ssr.js
directory=/opt/apps/nvnhan0810.com
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=/opt/apps/nvnhan0810.com/storage/logs/inertia-ssr.log
stopwaitsecs=10
startsecs=2
```

Khi đổi version fnm, cập nhật đường dẫn `command=.../node-versions/vXX.../installation/bin/node`.

Reload sau khi tạo/sửa config:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status nvnhan0810-ssr
```

Restart sau mỗi lần deploy có đổi SSR bundle:

```bash
sudo supervisorctl restart nvnhan0810-ssr
```

`deploy.sh` tự restart program `nvnhan0810-ssr` sau mỗi lần deploy.

## Kiểm tra SSR hoạt động

1. **Health / process**

   ```bash
   curl -s http://127.0.0.1:13714/health
   sudo supervisorctl status nvnhan0810-ssr
   tail -f storage/logs/inertia-ssr.log
   ```

2. **HTML có nội dung render sẵn** (trang public, ví dụ `/`, `/posts`)

   - Trình duyệt: **View Page Source** (không phải Inspect)
   - Hoặc: `curl -s https://nvnhan0810.com/posts | head -n 80`
   - HTML phải chứa nội dung bài viết/tiêu đề, không chỉ `<div id="app">` rỗng.

3. **Admin không SSR**

   - `/admin` vẫn SPA bình thường; middleware tắt `inertia.ssr.enabled` cho `admin/*`.

## Troubleshooting

| Triệu chứng | Gợi ý |
|-------------|--------|
| `STARTING` / `node: not found` trong log | Supervisor không thấy fnm; dùng **đường dẫn tuyệt đối** tới `node` trong fnm, `user=root` |
| `Cannot find package 'react'` | Bundle cũ chưa bundle deps; chạy lại `npm run build` (cần `ssr.noExternal: true` trong vite) rồi deploy `bootstrap/ssr/` |
| View source vẫn rỗng | `supervisorctl status`, `curl http://127.0.0.1:13714/health`, log `inertia-ssr.log` |
| `Inertia SSR bundle not found` | Chạy lại `npm run build` trước deploy; đảm bảo rsync không bỏ `bootstrap/ssr/` |
| Port conflict | Đổi `INERTIA_SSR_URL` nếu cần (mặc định `13714`) |

## Luồng tóm tắt

```
Request (public) → Laravel → @inertia → HTTP POST 127.0.0.1:13714/render
→ Node SSR bundle → HTML + head → response
```

Client hydrate qua `resources/ts/App.tsx` (`hydrateRoot`).
