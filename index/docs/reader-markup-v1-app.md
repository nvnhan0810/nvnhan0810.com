# Reader Markup v1 — tích hợp app (PDFKit + SwiftUI)

Web đã ship format portable **`reader.markup.v1`** (highlight vùng + ghi chú).  
PencilKit (`pencilkit.pkdrawing`) **giữ nguyên**. Freehand portable cho web: **`reader.ink.v1`** (app xuất, web chỉ xem) — xem [reader-ink-v1-app.md](./reader-ink-v1-app.md).

Ba format **cùng tồn tại trên một trang** (unique `(document_id, page_index, format)`).

Tài liệu này mô tả phần app cần làm để **xem / sửa / sync** markup từ web (và ngược lại).

---

## 1. Mục tiêu

| Việc | App cần |
|------|---------|
| Hiển thị highlight + note tạo trên web | Overlay trên `PDFView` / `PDFPage` |
| Tạo / sửa / xóa highlight + note trên app | Cùng schema JSON, PUT lên API |
| Freehand PencilKit | Không đổi — vẫn `pencilkit.pkdrawing` |
| Freehand trên web | App xuất thêm `reader.ink.v1` (derived); web chỉ display |
| Sync 2 chiều (markup) | Dùng revision + `X-Annotation-Format` |

**Không** cố parse / render PKDrawing trên web. Markup JSON là lớp highlight/note dùng chung; ink JSON là lớp freehand display.

---

## 2. Schema JSON (`reader.markup.v1`)

Một blob / trang (Seaweed key: `…/markups/page-{n}.json`).

```json
{
  "version": 1,
  "items": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "highlight",
      "rects": [
        { "x": 0.12, "y": 0.34, "w": 0.45, "h": 0.028 }
      ],
      "color": "#F7E08A",
      "note": "Ý chính — có thể null",
      "created_at": "2026-10-16T07:00:00Z",
      "updated_at": "2026-10-16T07:05:00Z"
    }
  ]
}
```

### Quy ước tọa độ (quan trọng)

- Origin: **góc trên-trái** của trang.
- Đơn vị: **chuẩn hóa 0…1** theo **chiều rộng / chiều cao trang đang hiển thị** (crop box / media box mà viewer dùng).
- `x,y` = góc trên-trái của rect; `w,h` = kích thước.
- Nhiều `rects` / item = multi-line highlight (web v1 thường 1 rect kéo tay).

### Map sang PDFKit

```swift
// pageBounds: PDFPage.bounds(for: .mediaBox) — hoặc cropBox nếu viewer dùng crop
func cgRect(from normalized: MarkupRect, pageBounds: CGRect) -> CGRect {
    let x = pageBounds.minX + normalized.x * pageBounds.width
    // PDFKit Y tăng lên trên; normalized Y tăng xuống dưới (như UIKit / web).
    let y = pageBounds.maxY - (normalized.y + normalized.h) * pageBounds.height
    let w = normalized.w * pageBounds.width
    let h = normalized.h * pageBounds.height
    return CGRect(x: x, y: y, width: w, height: h)
}
```

Chiếu ngược (user kéo trên PDFView → normalized) phải dùng **cùng** pageBounds / transform với lúc vẽ overlay.

---

## 3. API (đã có trên BE)

Base: `/api/v1` + Sanctum token (như annotations hiện tại).

### List metadata (mọi format)

`GET /documents/{id}/annotations`  
→ mỗi phần tử có `format` (`pencilkit.pkdrawing` | `reader.markup.v1` | `reader.ink.v1`).

### Download / upload theo format

Header bắt buộc khi làm việc với markup:

```http
X-Annotation-Format: reader.markup.v1
```

(Query `?format=reader.markup.v1` cũng được.)

| Method | Path | Body |
|--------|------|------|
| `GET` | `/documents/{id}/annotations/{pageIndex}` | Binary JSON |
| `PUT` | `/documents/{id}/annotations/{pageIndex}` | Raw JSON body |

PUT headers gợi ý:

```http
Content-Type: application/json
X-Annotation-Format: reader.markup.v1
If-Match: "{revision}"
X-Content-SHA256: <sha256 hex of body>
X-Empty: 1          # khi xóa hết items trang đó (body rỗng)
```

Response PUT có `format`, `revision`, `empty`, `content_sha256`.

Conflict `409` + `details.server_revision` → refetch GET rồi merge / LWW (web đang LWW bỏ `If-Match` rồi PUT lại).

### Sync cursor

`GET /sync/changes?since=…` đã trả `annotations[]` kèm `format` — app phải **không** giả định mọi annotation là PencilKit; branch theo `format`.

### DB uniqueness

`(document_id, page_index, format)` — một trang có thể có drawing PencilKit, ink JSON, **và** markup JSON.

---

## 4. Checklist SwiftUI / PDFKit

### Model

- [ ] `struct ReaderMarkupV1: Codable` (`version`, `items`)
- [ ] `struct MarkupItem` / `MarkupRect` (map `created_at` ↔ `createdAt` bằng `CodingKeys`)
- [ ] Store local: song song với `page-N.drawing` → ví dụ `page-N.markup.json`
- [ ] `enum AnnotationFormat: String` gồm `.pencilKit`, `.readerMarkupV1`, `.readerInkV1`

### Sync

- [ ] Khi pull annotations: nếu `format == reader.markup.v1` → download JSON, không đưa vào `PKDrawing`
- [ ] Khi push dirty markup → PUT kèm `X-Annotation-Format: reader.markup.v1`
- [ ] PencilKit push vẫn default / header `pencilkit.pkdrawing` (giữ BC)
- [ ] Tombstone / empty page: `X-Empty: 1` riêng **từng format** (xóa markup ≠ xóa drawing)

### UI (SwiftUI + PDFKit)

- [ ] Overlay layer trên `PDFView` (SwiftUI `overlay` / `UIViewRepresentable`)
- [ ] Vẽ `rects` bằng `Color` + opacity (màu từ `item.color`)
- [ ] Tap highlight → sheet/popover sửa `note`, xóa item
- [ ] Tool “Highlight”: kéo rect trên trang → tạo item mới (`UUID`, timestamps ISO8601 Z)
- [ ] (Tuỳ chọn) Highlight theo text selection `PDFSelection` → convert quads → `rects[]`
- [ ] Pencil tool / `PKCanvasView` **không** ghi vào markup JSON

### Hiển thị đồng thời

- [ ] Trang có cả drawing + markup: render PDF → PencilKit canvas (nếu có) → markup overlay (trên cùng hoặc dưới canvas tùy UX)
- [ ] iPhone read-only drawing: vẫn **cho phép** xem/sửa markup nếu product muốn (khác rule PencilKit cũ)

---

## 5. Web đã làm (tham chiếu)

- Format enum + unique `(doc, page, format)`
- API `X-Annotation-Format` trên GET/PUT annotations
- Admin JSON: `GET/PUT /admin/reader/documents/{id}/markups[/{pageIndex}]`
- Viewer web:
  - **Highlight từ**: bôi đen chữ trên text layer PDF → tạo `rects[]`
  - **Ghi chú vùng**: kéo vùng trên trang → **gõ trực tiếp trong vùng** (không popup tách)
  - Sync debounce qua `reader.markup.v1`
  - **Freehand**: render `reader.ink.v1` read-only (dưới markup); không UI pencil

Web **không** render `pencilkit.pkdrawing`. Freehand trên web = `reader.ink.v1` (app derived).

---

## 6. Thứ tự làm trên app (đề xuất)

1. Codable + local file store cho markup  
2. Sync pull/push nhánh `reader.markup.v1`  
3. Overlay read-only (xem highlight từ web)  
4. Edit note / xóa  
5. Tạo highlight (kéo hoặc text selection)  
6. Regression: PencilKit sync vẫn xanh  

---

## 7. Không làm trong v1 markup

- Convert PKDrawing → JSON (không hỗ trợ)
- Burn-in annotation vào PDF export
- Realtime collab (chỉ revision LWW / If-Match)
- Sticky notes không gắn rect (mọi note gắn `highlight` item)

---

## 8. Smoke test chéo

1. Web: highlight + note trang 3 → sync  
2. App: mở doc → thấy overlay đúng vị trí + note  
3. App: sửa note → web reload thấy  
4. App: vẽ PencilKit cùng trang → drawing + ink sync; không đè markup  
5. Web: thấy freehand từ `reader.ink.v1` dưới highlight  
6. Xóa hết markup trang (`items: []` / `X-Empty: 1`) → web hết highlight, drawing/ink còn  
