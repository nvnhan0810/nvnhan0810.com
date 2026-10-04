# Apple Reader — API / Backend Spec (SSO + PDF + Annotation Sync)

Tài liệu để implement BE **trước** khi nối iOS app.  
Không mô tả UI app; chỉ contract auth, storage, sync.

**Liên quan sẵn có:** [sso.md](./sso.md) (IdP trên `index` / `nvnhan0810.com`).

---

## 1. Mục tiêu

| Mục tiêu | Ghi chú |
|----------|---------|
| Login SSO qua `nvnhan0810.com` | Cùng IdP Google OAuth như wallets/FLC/todo |
| Sync PDF đa thiết bị | Binary PDF lưu **SeaweedFS**; metadata trong DB |
| Sync annotation | PencilKit `PKDrawing` binary theo **từng trang** + metadata revision |
| Sync reading progress | Trang đang đọc |
| iPhone read-only phía app | BE vẫn sync annotation (để xem nét từ iPad); app iPhone không tạo/sửa |

**Out of scope v1:** share document giữa nhiều user, OCR, version history dài, collaborative editing realtime.

---

## 2. Kiến trúc đề xuất

```text
┌─────────────┐     ASWebAuth      ┌──────────────────────┐
│ iOS / iPadOS│ ─────────────────► │ IdP: nvnhan0810.com  │
│ NhanApp     │ ◄──── code ─────── │ (index SSO module)   │
└──────┬──────┘                    └──────────┬───────────┘
       │                                      │
       │ Bearer token                         │ POST /api/auth/sso/token
       ▼                                      │ (client_secret server-side)
┌──────────────────────┐                      │
│ Reader API (Laravel) │ ◄────────────────────┘ exchange code → user claims
│ Sanctum PAT          │
│ Postgres metadata    │
└──────────┬───────────┘
           │ put/get object
           ▼
┌──────────────────────┐
│ SeaweedFS (S3 API)   │
│ PDF + drawings + thumbs
└──────────────────────┘
```

### 2.1. Nên đặt Reader API ở đâu?

**Khuyến nghị:** service/module riêng `reader-api` (Laravel + Sanctum), **không** nhét logic sync PDF vào IdP `index`.

Lý do:
- IdP chỉ làm auth (đã có).
- SeaweedFS + upload lớn + sync conflict thuộc domain Reader.
- Pattern giống FLC mobile: IdP cấp `code` → app con exchange → **Sanctum token của app con**.

Có thể host subdomain ví dụ: `https://reader-api.nvnhan0810.com` (hoặc path trên infra hiện có).

### 2.2. Vai trò từng tầng

| Thành phần | Trách nhiệm |
|------------|-------------|
| **IdP (`index`)** | Google login, SSO authorize/code, `POST /api/auth/sso/token` → claims `{sub,email,name,avatar}` |
| **Reader API** | Exchange code → Sanctum token; CRUD documents; upload/download PDF; sync annotations & progress |
| **Postgres** | User mapping, document metadata, page annotation revisions, progress, sync cursors |
| **SeaweedFS** | Object storage (PDF, `page-N.drawing`, thumbnail) |

---

## 3. SSO — việc cần làm trên IdP (`index`)

### 3.1. Tạo SSO client

Admin → SSO clients (đã có UI), tạo client:

| Field | Giá trị đề xuất |
|-------|-----------------|
| `client_id` | `apple-reader` |
| `name` | Apple Reader |
| `enabled` | true |
| `redirect_uris` | (có thể để trống nếu dùng pattern) |
| `redirect_uri_patterns` | regex cho deep link, ví dụ: `^nvnhan0810://oauth-callback$` và/hoặc `^com\\.nvnhan0810\\.NhanApp(\\.)?.*://oauth-callback$` |

Dùng chung `SSO_SECRET` (đã có trên index).

### 3.2. Flow native (bám FLC mobile)

1. App mở browser / `ASWebAuthenticationSession`:
   - `GET https://nvnhan0810.com/auth/sso/authorize?client_id=apple-reader&redirect_uri=nvnhan0810://oauth-callback&response_type=code&state=...`
2. User login Google trên IdP (allowlist `valid_emails`).
3. Redirect về app: `nvnhan0810://oauth-callback?code=...&state=...`
4. **Reader API** (không phải app gọi thẳng secret) nhận `code` từ app và:
   - Gọi IdP `POST /api/auth/sso/token` với `client_id`, `client_secret` (= `SSO_SECRET`), `code`, `redirect_uri`, `grant_type=authorization_code`
   - Nhận claims → upsert user → cấp Sanctum personal access token
   - Trả token về app

> `client_secret` **không** embed trong iOS binary. Chỉ nằm ở Reader API env.

### 3.3. Endpoint Reader API — Auth

Base: `https://reader-api.nvnhan0810.com` (placeholder).

#### `POST /v1/auth/sso/exchange`

App đổi authorization code lấy Bearer token.

**Request**

```json
{
  "code": "…",
  "redirect_uri": "nvnhan0810://oauth-callback",
  "device_name": "iPad Pro",
  "client_id": "apple-reader"
}
```

**Response `200`**

```json
{
  "token": "1|xxxxxxxx",
  "token_type": "Bearer",
  "user": {
    "id": "uuid-or-int",
    "email": "you@example.com",
    "name": "Nguyen Van Nhan",
    "avatar": "https://…"
  }
}
```

#### `GET /v1/me`

Header: `Authorization: Bearer {token}`

#### `POST /v1/auth/logout`

Revoke current Sanctum token.

---

## 4. Data model (Postgres)

### 4.1. `users` (Reader API local)

Map từ IdP `sub` (int trên index).

| Column | Type | Note |
|--------|------|------|
| `id` | uuid PK | |
| `idp_sub` | bigint unique | claims.`sub` từ IdP |
| `email` | string unique | |
| `name` | string | |
| `avatar_url` | string nullable | |
| `created_at` / `updated_at` | timestamptz | |

### 4.2. `documents`

Khớp gần với `DocumentItem` trên iOS.

| Column | Type | Note |
|--------|------|------|
| `id` | uuid PK | Client có thể generate UUID khi import offline rồi sync |
| `user_id` | uuid FK | owner |
| `title` | string | |
| `page_count` | int | |
| `content_type` | string | `application/pdf` |
| `byte_size` | bigint | |
| `content_sha256` | char(64) | dedupe / integrity |
| `seaweed_pdf_key` | string | object key PDF |
| `seaweed_thumb_key` | string nullable | |
| `status` | enum | `ready`, `uploading`, `failed`, `deleted` |
| `revision` | bigint | tăng mỗi lần metadata/content đổi |
| `deleted_at` | timestamptz nullable | soft delete |
| `created_at` / `updated_at` | timestamptz | |
| `last_opened_at` | timestamptz nullable | optional |

Index: `(user_id, updated_at)`, `(user_id, deleted_at)`.

### 4.3. `reading_progress`

| Column | Type | Note |
|--------|------|------|
| `document_id` | uuid PK/FK | |
| `user_id` | uuid | denormalize cho query nhanh |
| `page_index` | int | 0-based (giống app) |
| `updated_at` | timestamptz | |
| `revision` | bigint | |

### 4.4. `page_annotations`

Một row / (document, page). Binary nằm SeaweedFS.

| Column | Type | Note |
|--------|------|------|
| `id` | uuid PK | |
| `document_id` | uuid FK | |
| `user_id` | uuid | |
| `page_index` | int | 0-based |
| `format` | string | v1: `pencilkit.pkdrawing` |
| `seaweed_key` | string nullable | null = trang không còn nét |
| `byte_size` | int | |
| `content_sha256` | char(64) nullable | |
| `revision` | bigint | tăng mỗi lần ghi trang |
| `updated_at` | timestamptz | |
| Unique | `(document_id, page_index)` | |

### 4.5. (Optional) `sync_devices`

| Column | Type |
|--------|------|
| `id` | uuid |
| `user_id` | uuid |
| `device_name` | string |
| `platform` | `ios` / `ipados` |
| `last_sync_at` | timestamptz |

---

## 5. SeaweedFS layout

Dùng **S3-compatible API** của SeaweedFS (Laravel disk `s3`).

Bucket ví dụ: `reader`

```text
reader/
  users/{user_id}/
    documents/{document_id}/
      original.pdf
      thumb.jpg
      drawings/
        page-0.drawing
        page-12.drawing
```

### Object key helpers

- PDF: `users/{userId}/documents/{docId}/original.pdf`
- Thumb: `users/{userId}/documents/{docId}/thumb.jpg`
- Drawing: `users/{userId}/documents/{docId}/drawings/page-{n}.drawing`

### Env Reader API

```env
FILESYSTEM_DISK=seaweed
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=reader
AWS_ENDPOINT=https://seaweedfs.example:8333
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### Upload strategy (khuyến nghị v1)

**App upload trực tiếp qua Reader API** (multipart), API stream lên SeaweedFS — đơn giản, đủ cho personal use.

v2 (nếu file rất lớn): **presigned PUT URL** từ Reader API → app upload thẳng SeaweedFS → `POST .../complete`.

Giới hạn v1 đề xuất: PDF ≤ 200MB; drawing/page ≤ 5MB.

---

## 6. Annotation format (quan trọng)

App hiện lưu:

```text
PKDrawing.dataRepresentation() → file page-{n}.drawing
```

**BE v1 phải giữ nguyên binary này** (opaque blob). Không cố parse PencilKit phía server.

| Field | Value |
|-------|--------|
| `format` | `pencilkit.pkdrawing` |
| Content-Type upload | `application/octet-stream` |
| Empty page | xóa object + set `seaweed_key=null` (hoặc DELETE annotation) |

> Export PDF đã burn-in annotation: **hoãn** (đúng với MVP app).

---

## 7. Sync protocol

### 7.1. Nguyên tắc

- **Source of truth:** server sau khi login.
- **Conflict:** last-write-wins theo `revision` (optimistic concurrency).
- Client gửi `If-Match: {revision}` hoặc body `base_revision` khi update.
- Nếu conflict → `409` + bản server hiện tại; client merge thủ công hoặc overwrite có confirm (v1: overwrite nếu client chọn).

### 7.2. Pull changes

#### `GET /v1/sync/changes?since={ISO8601 or cursor}`

Trả về delta metadata (không blob lớn).

```json
{
  "server_time": "2026-10-04T15:00:00Z",
  "next_cursor": "2026-10-04T15:00:00Z",
  "documents": [
    {
      "id": "…",
      "title": "…",
      "page_count": 120,
      "revision": 3,
      "updated_at": "…",
      "deleted_at": null,
      "byte_size": 1234567,
      "content_sha256": "…",
      "has_thumbnail": true
    }
  ],
  "progress": [
    { "document_id": "…", "page_index": 10, "revision": 2, "updated_at": "…" }
  ],
  "annotations": [
    {
      "document_id": "…",
      "page_index": 3,
      "format": "pencilkit.pkdrawing",
      "revision": 5,
      "updated_at": "…",
      "empty": false,
      "content_sha256": "…"
    }
  ]
}
```

Client sau đó:

- Download PDF nếu thiếu / `content_sha256` khác: `GET /v1/documents/{id}/file`
- Download drawing nếu revision local thấp hơn: `GET /v1/documents/{id}/annotations/{page}`
- Download thumb optional: `GET /v1/documents/{id}/thumbnail`

### 7.3. Push

Thứ tự khuyến nghị:

1. Tạo/ensure document metadata  
2. Upload PDF (nếu mới / đổi content)  
3. Upload annotations từng trang đã dirty  
4. Push reading progress  

---

## 8. REST API — Documents

Tất cả endpoint (trừ exchange) yêu cầu:

```http
Authorization: Bearer {token}
Accept: application/json
```

### `GET /v1/documents`

Query: `?cursor=&limit=50`

Danh sách documents của user (không gồm soft-deleted, trừ `?include_deleted=1`).

### `POST /v1/documents`

Tạo metadata trước khi upload file (hoặc kèm multipart một shot).

**JSON create**

```json
{
  "id": "optional-client-uuid",
  "title": "My Book",
  "page_count": 0
}
```

**Response `201`:** document object + `upload` hints.

### `POST /v1/documents/{id}/file`

`multipart/form-data`:

- `file`: PDF
- `content_sha256`: optional (server tự hash nếu thiếu)
- `page_count`: int

Server: validate PDF, store SeaweedFS, update `byte_size`, `content_sha256`, `page_count`, `revision++`, `status=ready`.

### `GET /v1/documents/{id}`

Metadata only.

### `GET /v1/documents/{id}/file`

Stream PDF (`application/pdf`) hoặc **302** sang signed Seaweed URL (TTL ngắn, ví dụ 15 phút).

### `PUT /v1/documents/{id}`

Đổi title / metadata. Body có `base_revision`.

### `DELETE /v1/documents/{id}`

Soft delete + (async job) xóa objects SeaweedFS.

### `POST /v1/documents/{id}/thumbnail`

Upload JPEG thumbnail (app đã generate sẵn — khớp `ThumbnailService`).

### `GET /v1/documents/{id}/thumbnail`

---

## 9. REST API — Annotations

### `GET /v1/documents/{id}/annotations`

Manifest mọi trang có nét:

```json
{
  "document_id": "…",
  "pages": [
    { "page_index": 0, "revision": 2, "empty": false, "content_sha256": "…" },
    { "page_index": 4, "revision": 1, "empty": false, "content_sha256": "…" }
  ]
}
```

### `GET /v1/documents/{id}/annotations/{pageIndex}`

Binary `application/octet-stream` (`pencilkit.pkdrawing`).  
`404` nếu empty/không có.

Headers hữu ích: `ETag: "{revision}"`, `X-Content-SHA256: …`

### `PUT /v1/documents/{id}/annotations/{pageIndex}`

Body: raw binary drawing.

Headers/query:

- `Content-Type: application/octet-stream`
- `X-Annotation-Format: pencilkit.pkdrawing`
- `If-Match: {base_revision}` (optional lần đầu có thể bỏ)
- `X-Content-SHA256: …` (optional)

Empty body hoặc header `X-Empty: 1` → xóa nét trang đó.

**Response `200`**

```json
{ "page_index": 3, "revision": 6, "empty": false, "updated_at": "…" }
```

**`409 Conflict`** nếu `If-Match` không khớp.

### `PUT /v1/documents/{id}/annotations:batch` (optional v1.1)

Nhiều trang trong một request (multipart) để giảm round-trip khi sync.

---

## 10. REST API — Reading progress

### `GET /v1/documents/{id}/progress`

```json
{ "document_id": "…", "page_index": 10, "revision": 2, "updated_at": "…" }
```

### `PUT /v1/documents/{id}/progress`

```json
{ "page_index": 11, "base_revision": 2 }
```

LWW theo `updated_at`/`revision`. Conflict hiếm — có thể luôn accept nếu client `updated_at` mới hơn (đơn giản hóa v1).

---

## 11. Error contract

```json
{
  "error": {
    "code": "conflict",
    "message": "Annotation revision mismatch",
    "details": { "server_revision": 6 }
  }
}
```

| HTTP | code | Khi nào |
|------|------|---------|
| 401 | `unauthenticated` | thiếu/sai token |
| 403 | `forbidden` | không phải owner |
| 404 | `not_found` | |
| 409 | `conflict` | revision mismatch |
| 413 | `payload_too_large` | |
| 422 | `validation_error` | |
| 429 | `rate_limited` | |

---

## 12. Checklist implement BE (thứ tự)

### Phase A — Auth bridge

- [ ] Đăng ký SSO client `apple-reader` + redirect pattern trên IdP
- [ ] Scaffold Reader API (Laravel) + Sanctum
- [ ] Env: `SSO_IDP_URL`, `SSO_SECRET`, `SSO_CLIENT_ID=apple-reader`
- [ ] `POST /v1/auth/sso/exchange` (server-side gọi IdP `/api/auth/sso/token`)
- [ ] `GET /v1/me`, `POST /v1/auth/logout`
- [ ] Test: Postman/curl với code thật từ authorize URL

### Phase B — Storage

- [ ] SeaweedFS bucket + credentials
- [ ] Laravel disk S3 path-style
- [ ] Helper build object keys + signed URL (nếu dùng)
- [ ] Smoke: put/get 1 file PDF nhỏ

### Phase C — Documents

- [ ] Migrations `users`, `documents`
- [ ] CRUD metadata + upload/download PDF + thumbnail
- [ ] Soft delete + ownership checks
- [ ] SHA-256 integrity

### Phase D — Annotations + progress

- [ ] Migrations `page_annotations`, `reading_progress`
- [ ] PUT/GET per-page drawing (opaque binary)
- [ ] Empty page = delete object
- [ ] Progress PUT/GET
- [ ] Optimistic `revision` + `409`

### Phase E — Sync cursor

- [ ] `GET /v1/sync/changes?since=`
- [ ] Đảm bảo index `(user_id, updated_at)` đủ nhanh
- [ ] Document rate limits cơ bản

### Phase F — Ops

- [ ] Logging (không log binary)
- [ ] Backup DB; lifecycle SeaweedFS
- [ ] OpenAPI/Swagger export từ spec này
- [ ] Staging URL cho iOS Debug (`NhanApp Dev`)

---

## 13. Mapping với model iOS hiện tại (tham chiếu client sau này)

| iOS local | Server |
|-----------|--------|
| `DocumentItem.id` | `documents.id` |
| `DocumentItem.title` | `documents.title` |
| `DocumentItem.pageCount` | `documents.page_count` |
| file `Documents/PDFs/*.pdf` | Seaweed `original.pdf` |
| `ReadingProgress.pageIndex` | `reading_progress.page_index` |
| `Drawings/{id}/page-N.drawing` | Seaweed `drawings/page-N.drawing` + `page_annotations` |

Offline-first (sau khi có API): import local → khi login, push document mới lên server; pull delta về thiết bị khác.

---

## 14. Bảo mật & quota (v1)

- Chỉ owner đọc/ghi document của mình.
- Allowlist email vẫn do IdP enforce (login fail nếu ngoài list).
- Token Sanctum expires / revoke on logout.
- Quota gợi ý: 5GB / user hoặc 200 documents (config).
- Không expose Seaweed admin; chỉ signed URL TTL ngắn hoặc proxy stream qua API.

---

## 15. Quyết định đã chốt trong spec này

1. IdP = `nvnhan0810.com` SSO hiện có; Reader API riêng + Sanctum.  
2. PDF + PKDrawing blobs → **SeaweedFS**; metadata/revision → **Postgres**.  
3. Annotation = opaque `pencilkit.pkdrawing` per page.  
4. Sync = delta `since` + LWW/`revision`.  
5. Secret SSO chỉ nằm server Reader API.

---

## 16. Việc IdP cần làm thêm (nhỏ)

Ngoài tạo client `apple-reader`, thường **không** cần đổi core SSO.  
Nếu muốn DX giống FLC:

- Optional trên IdP: `GET /api/auth/sso/redirect?client_id=apple-reader&redirect_uri=…` (shortcut build authorize URL) — **không bắt buộc** nếu app tự build authorize URL.

---

## 17. OpenAPI skeleton (paths)

```text
POST   /v1/auth/sso/exchange
GET    /v1/me
POST   /v1/auth/logout

GET    /v1/documents
POST   /v1/documents
GET    /v1/documents/{id}
PUT    /v1/documents/{id}
DELETE /v1/documents/{id}
POST   /v1/documents/{id}/file
GET    /v1/documents/{id}/file
POST   /v1/documents/{id}/thumbnail
GET    /v1/documents/{id}/thumbnail

GET    /v1/documents/{id}/annotations
GET    /v1/documents/{id}/annotations/{pageIndex}
PUT    /v1/documents/{id}/annotations/{pageIndex}

GET    /v1/documents/{id}/progress
PUT    /v1/documents/{id}/progress

GET    /v1/sync/changes
```

Khi implement xong Phase A–E, iOS mới cần: SSO session + sync client. App code hiện tại **chưa** cần sửa cho đến khi BE sẵn sàng.
