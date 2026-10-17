# `reader.ink.v1` — web read-only freehand

App vẽ bằng PencilKit (`pencilkit.pkdrawing`). Mỗi lần lưu, app **xuất thêm** bản portable `reader.ink.v1` để web **chỉ hiển thị** (không vẽ/sửa trên web).

Highlight + note vẫn dùng `reader.markup.v1` (không đổi).

## Formats trên một trang

| Format | Ai ghi | Ai đọc |
|--------|--------|--------|
| `pencilkit.pkdrawing` | App | App (edit) |
| `reader.ink.v1` | App (derived) | Web (display) |
| `reader.markup.v1` | App + Web | App + Web |

Unique: `(document_id, page_index, format)`.

## Schema

```json
{
  "version": 1,
  "strokes": [
    {
      "color": "#1A1A1A",
      "width": 0.004,
      "opacity": 1,
      "points": [[0.12, 0.34], [0.13, 0.35]]
    }
  ]
}
```

- **Origin:** góc trên-trái trang.
- **Đơn vị:** chuẩn hóa `0…1` theo rộng/cao trang (cùng quy ước markup).
- **`width`:** độ dày nét / chiều rộng trang (`inkWidth / pageWidth`).
- **`points`:** polyline `[x, y]`; web nối bằng line/quadratic đơn giản là đủ.
- Trang trống: `strokes: []` hoặc PUT với `X-Empty: 1`.

## API

Header bắt buộc:

```http
X-Annotation-Format: reader.ink.v1
```

| Method | Path | Ghi chú |
|--------|------|---------|
| `GET` | `/api/v1/documents/{id}/annotations/{pageIndex}` | `Accept: application/json` |
| `PUT` | `/api/v1/documents/{id}/annotations/{pageIndex}` | Body JSON; web **không** PUT |

PUT (app only) gợi ý:

```http
Content-Type: application/json
X-Annotation-Format: reader.ink.v1
If-Match: "{revision}"
X-Content-SHA256: <sha256 hex>
X-Empty: 1
```

`GET /api/v1/sync/changes` trả `annotations[]` kèm `format` — web branch theo `reader.ink.v1`.

Admin (web viewer, session cookie):

| Method | Path |
|--------|------|
| `GET` | `/admin/reader/documents/{id}/inks` |
| `GET` | `/admin/reader/documents/{id}/inks/{pageIndex}` |

Seaweed key: `reader/users/{userId}/documents/{docId}/inks/page-{n}.json`

## Render trên web

- Canvas overlay dưới markup highlight (`z` thấp hơn markup).
- Không UI pencil trên web.

## Checklist app

- [ ] Khi lưu PencilKit: derive + PUT `reader.ink.v1` cùng trang (revision riêng theo format)
- [ ] Tombstone: `X-Empty: 1` / `strokes: []` khi xóa drawing
- [ ] Không convert ngược ink → PencilKit
- [ ] Sync pull: bỏ qua ink nếu app chỉ cần PKDrawing (ink là derived cho web)

## Không hỗ trợ

- Ink → PencilKit (không convert ngược)
- Freehand edit trên web
- Pressure / tilt styling
