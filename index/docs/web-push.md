# Web Push (Pomodoro / Matrix)

Hướng dẫn đăng ký và vận hành **Web Push** cho Eisenhower Matrix + Pomodoro trên `nvnhan0810.com` — **không cần native app**, không tốn phí push service của trình duyệt.

## Chi phí

| Thành phần | Phí |
| --- | --- |
| Push service Chrome / Firefox / Safari (endpoint trình duyệt) | Free |
| VAPID key pair | Free (tự generate) |
| `minishlink/web-push` (PHP) | Free |
| Vendor SaaS (OneSignal, …) | Không dùng |

Chỉ tốn hosting/queue bạn đã chạy (`queue:work` + `schedule:work`).

## iOS / Safari — vì sao tab thường không có noti

| Môi trường | Notifications / Web Push |
| --- | --- |
| Desktop Chrome / Firefox | OK (xin quyền) |
| macOS Safari 16.4+ | OK trong tab |
| **iOS Safari (tab)** | **Không** — không subscribe được |
| **iOS 16.4+ PWA (Add to Home Screen)** | OK — mở từ icon Home Screen |

Trên iPhone: Share → **Add to Home Screen** → mở app từ icon → bật thông báo trong Matrix.

## Luồng đăng ký

```text
1. Tạo VAPID public/private (1 lần) → env
2. User mở PWA / trình duyệt hỗ trợ → bấm "Bật thông báo"
3. pushManager.subscribe({ applicationServerKey: VAPID_public })
4. Browser trả subscription (endpoint + keys) → POST /matrix/web-push/subscribe
5. Server lưu theo user_id
6. Khi hết phase Pomodoro: job gửi push (ký VAPID_private) → SW showNotification
7. Click noti → mở /matrix (không về home trừ khi không cấu hình)
```

## Suppress khi đang nhìn Matrix

Hai lớp:

1. **Server**: presence heartbeat; bỏ qua subscription có `last_focused_at` gần đây.
2. **Service worker**: nếu có window `/matrix` đang `focused` → không `showNotification`.

Rule theo **từng thiết bị**: laptop đang focus Matrix thì không noti laptop; điện thoại vẫn có thể nhận.

## Click noti

`notificationclick` trong `public/sw.js` mở `/matrix` (hoặc `data.url`), ưu tiên focus tab Matrix đã mở.

## Env

```env
VAPID_SUBJECT=mailto:you@example.com
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

Generate:

```bash
php artisan webpush:vapid
```

(In ra cặp key — copy vào `.env`, rồi `php artisan config:clear`.)

## API (auth)

| Method | Path | Mô tả |
| --- | --- | --- |
| GET | `/matrix/web-push/vapid-public-key` | Public key (hoặc lấy từ Inertia props) |
| POST | `/matrix/web-push/subscribe` | Lưu subscription |
| DELETE | `/matrix/web-push/subscribe` | Huỷ theo endpoint |
| POST | `/matrix/web-push/presence` | `{ focused: bool }` heartbeat |

## PWA files

- `public/manifest.webmanifest`
- `public/sw.js`
- Link manifest + `apple-mobile-web-app-capable` trong `resources/views/app.blade.php`

## Vận hành

- Cần **HTTPS**, `queue:work` (delayed job tới `endsAt`), worker đang chạy.
- Khi pause / đổi timer: job cũ tự no-op (so khớp `endsAt`).
- Local `Notification` trong tab (desktop) vẫn là fallback khi tab còn sống nhưng không focus.

## Checklist thử trên iPhone

1. iOS ≥ 16.4, HTTPS production/dev
2. Safari → Share → Add to Home Screen
3. Mở từ Home Screen (standalone)
4. Matrix → menu → Bật thông báo → Allow
5. Start Pomodoro, khoá màn hình → hết phase phải có banner
6. Mở Matrix đang focus → không spam noti trên máy đó
