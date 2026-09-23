# Web Push / Pomodoro (Matrix)

Đăng ký VAPID, PWA, và timer **server-authoritative** cho Eisenhower Matrix trên `nvnhan0810.com` — không native app, không SaaS push.

---

## 1. Yêu cầu sản phẩm

| Môi trường | Hành vi |
| --- | --- |
| Tab/PWA đang **focus Matrix** (bất kỳ thiết bị) | Hết phase → **không** Web Push (mọi máy) |
| Không ai focus ≥ 15s | Hết phase → **Web Push** tới mọi subscription |
| FE | Chỉ countdown theo `endsAt`; **không** tự advance phase |
| iOS Safari (tab thường) | Không subscribe Web Push |
| iOS 16.4+ PWA (Add to Home Screen) | OK — mở từ icon Home Screen |

### Verdict thiết kế

| Câu hỏi | Trả lời |
| --- | --- |
| Ai advance phase? | **Chỉ BE** (job hoặc skip) |
| Focus suppress | **Global theo user** — 1 máy focus → không push mọi máy |
| Job khi đang focus? | Vẫn advance + SSE; **chỉ bỏ push** (không fail/retry) |
| FE hydrate khi PWA chết / SSE đứt? | **GET** (+ SSE reconnect) |

---

## 2. Chi phí

| Thành phần | Phí |
| --- | --- |
| Push Chrome / Firefox / Safari | Free |
| VAPID key pair | Free |
| `minishlink/web-push` (PHP) | Free |
| Vendor SaaS (OneSignal, …) | Không dùng |

Chỉ tốn hosting/queue (`queue:work`).

---

## 3. Flow timer + push

```text
START / RESUME
  FE → POST /matrix/pomodoro/start
  BE: tạo session_uuid, endsAt, activeTodoId, isRunning
      cancel job cũ → delayed job(session_uuid, endsAt, fromPhase)
      bump PomodoroStreamVersion → SSE event:pomodoro
  FE: nhận SSE → lưu uuid + endsAt → countdown local

FOCUS (Matrix visible + hasFocus)
  FE mỗi ~10s → POST /matrix/pomodoro/focus { sessionUuid, focused: true }
  BE: uuid khớp → stamp last_focused_at = now
  focused:false → no-op (TTL tự hết; tránh 1 máy blur xoá focus máy khác)

SETTINGS (đang chạy)
  FE → PUT /matrix/pomodoro/settings
  BE: clamp remaining, endsAt + session_uuid mới, cancel/schedule job → SSE

PAUSE
  FE → POST /matrix/pomodoro/pause
  BE: remainingMs = endsAt-now, clear endsAt/session_uuid, cancel job → SSE

SKIP
  FE → POST /matrix/pomodoro/skip
  BE: advance (không push) → schedule nếu running → SSE

JOB đến hạn
  Guard: job.session_uuid === state.session_uuid && isRunning && endsAt khớp
  Luôn: advance, session_uuid mới, save, SSE, schedule phase sau
  Push chỉ khi: last_focused_at null HOẶC (now - last_focused_at) >= FOCUS_TTL (15s)

FE
  Running: remaining = max(0, endsAt - Date.now())  // chỉ đồng hồ client
  Paused:  remaining = remainingMs (frozen; endsAt null)
  remaining==0 → chờ SSE/GET (không advance local)
  Âm thanh phase-end khi server đổi phase
```

API trả `endsAt` gốc (lúc job chạy); không derive `remainingMs` bằng đồng hồ server lúc serialize.

### Đăng ký Web Push

```text
1. VAPID public/private → env
2. User bấm "Bật thông báo"
3. pushManager.subscribe({ applicationServerKey })
4. POST /matrix/web-push/subscribe → lưu theo user_id
5. Hết phase + không focus → job push → SW showNotification
6. Click noti → /matrix
```

Service worker **luôn** `showNotification` (iOS); suppress chỉ ở server. Local `Notification` chỉ khi chưa có PushSubscription.

### So với flow cũ

| Cũ | Mới |
| --- | --- |
| FE + BE cùng advance → `X→X` / `X→Y` | Một writer (BE) |
| Mỗi sync schedule thêm job | Cancel/replace theo `session_uuid` |
| Focus per-endpoint | Focus global user |
| Job stale vẫn push | UUID không khớp → no-op |

---

## 4. API (auth)

| Method | Path | Mô tả |
| --- | --- | --- |
| GET | `/matrix/web-push/vapid-public-key` | Public key |
| GET | `/matrix/web-push/status` | Số subscription |
| POST | `/matrix/web-push/subscribe` | Lưu subscription |
| DELETE | `/matrix/web-push/subscribe` | Huỷ theo endpoint |
| GET | `/matrix/pomodoro` | Snapshot (+ reconcile overdue) |
| POST | `/matrix/pomodoro/start` | Start/resume; optional `activeTodoId` |
| POST | `/matrix/pomodoro/pause` | Pause + cancel job |
| POST | `/matrix/pomodoro/skip` | Advance phase |
| POST | `/matrix/pomodoro/reset` | Idle + clear active todo |
| PUT | `/matrix/pomodoro/settings` | Settings / recalc endsAt |
| PATCH | `/matrix/pomodoro/active-todo` | `{ activeTodoId }` |
| POST | `/matrix/pomodoro/focus` | `{ sessionUuid, focused }` ~10s |

Runtime: `endsAt` = ms job sẽ chạy (source of truth khi running). `remainingMs` chỉ dùng khi pause (`endsAt` null). FE tự `endsAt - Date.now()`.

---

## 5. Focus global + job

- Cột `todo_pomodoro_states.last_focused_at` (không dùng per-subscription để quyết định push).
- TTL **15s** (`WEB_PUSH_FOCUS_TTL_SECONDS`) — heartbeat 10s < TTL.
- Job: `SendPomodoroPhasePushJob(userId, sessionUuid, expectedEndsAtMs, fromPhase)`.
- Cancel: xoá pending `jobs` payload chứa uuid cũ + guard UUID lúc handle.
- FCM topic: `pomodoro-{sessionUuid}`.

---

## 6. Env & PWA

```env
VAPID_SUBJECT=mailto:you@example.com
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
WEB_PUSH_FOCUS_TTL_SECONDS=15
```

```bash
php artisan webpush:vapid   # copy vào .env → config:clear
```

Files: `public/manifest.webmanifest`, `public/sw.js`, manifest + `apple-mobile-web-app-capable` trong `app.blade.php`.

Cần **HTTPS**, Redis (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`), và Supervisor `queue:work redis`. Redis phải **ngoài app pod** (PVC / managed) — không để queue/cache trong ephemeral storage của Pod.

---

## 7. Giới hạn nền tảng

- Android Doze có thể deliver push muộn 10–15 phút dù server gửi đúng giờ.
- SSE reconnect ~120s — FE GET khi visible lại.
- PWA iOS suspend JS → chỉ Web Push / GET; local Notification không đủ.
- Screen Wake Lock (`useWakeLock`) chỉ khi app visible — không thay push.

---

## 8. Checklist verify

1. Start → DB có `session_uuid` + 1 delayed job; SSE đẩy uuid.
2. Settings mid-run → job cũ hủy/no-op; 1 job mới.
3. Focus bất kỳ máy → hết phase **không** push; mọi máy blur ≥15s → có push.
4. Job lúc focused: log skip push; phase đổi + SSE.
5. FE không tự đổi phase khi countdown = 0.
6. Pause → không job hợp lệ; không push.
7. iPhone: Add to Home Screen → Bật Web Push → khoá màn (không máy focus) → có banner.

---

## 9. File chính

| Layer | Path |
| --- | --- |
| Commands | `modules/Todo/Application/*Pomodoro*.php` |
| Schedule / cancel | `SchedulePomodoroPhasePush`, `CancelPomodoroPhaseJobs` |
| Deliver / reconcile | `DeliverPomodoroPhasePush`, `ReconcileOverduePomodoro` |
| Job | `app/Jobs/Todo/SendPomodoroPhasePushJob.php` |
| FE | `resources/ts/domains/todo/presentation/hooks/usePomodoro.ts` |
| SW | `public/sw.js` |
