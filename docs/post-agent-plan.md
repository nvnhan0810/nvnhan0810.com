# Post Agent

> **Đã có:** Chat agent trong admin (`/admin/posts/create`, `/edit`) — EN + VI, sticky panel, cancel.  
> **Đã có:** Post Agent API token-based để tạo draft / update / publish bài viết (dùng cho agent local).
> **Chưa có:** MCP server local + skill wiring (phần MCP package).

---

## Hai luồng

| Luồng | Trạng thái | Mô tả ngắn |
|-------|------------|------------|
| **A. Chat admin** | ✅ Done | Bạn chat trên web → Cursor Cloud sửa form → bấm **Lưu** ghi DB |
| **B. MCP local** | 📋 Kế hoạch | Agent Cursor trên máy → gọi API blog → tạo draft → bạn verify → publish |

Luồng A dùng session đăng nhập + `CURSOR_API_KEY` trên server.  
Luồng B cần **Post Agent API** + **MCP server** — agent local không đi qua trình duyệt.

---

## MCP local là gì?

**MCP (Model Context Protocol)** là cách Cursor IDE cho agent **gọi tool bên ngoài** (API, script, DB…).

Với blog, ý tưởng là cài một **MCP server nhỏ** trên máy bạn. Agent local đọc code/session, rồi dùng tool kiểu `create_post_draft` để đẩy bài lên web — không cần copy-paste thủ công.

```
Bạn (local)          Cursor Agent          MCP server          Website
    │                     │                    │                  │
    │ "Tổng hợp bài fix X"│                    │                  │
    ├────────────────────►│                    │                  │
    │                     │ create_post_draft  │                  │
    │                     ├───────────────────►│ POST /api/agent  │
    │                     │                    ├─────────────────►│
    │                     │◄── link /admin/.../edit ────────────│
    │◄── "Mở link verify" │                    │                  │
    │ Mở admin, chỉnh, Lưu│                    │                  │
```

**MCP = tay chân** (gọi HTTP). **Skill** (tuỳ chọn) = hướng dẫn agent *khi nào* tạo draft, *không* tự publish.

---

## Workflow điển hình

1. Bạn fix bug / làm feature trên repo local.
2. Nhắn agent: *"Viết bài tóm tắt, tạo draft EN + VI lên blog"*.
3. Agent đọc diff, commit, file đã đổi → gọi `create_post_draft`.
4. Server tạo bài `is_published=false` → trả link `/admin/posts/{id}/edit`.
5. Bạn mở link, đọc/sửa, bật Publish + **Lưu**.
6. (Tuỳ chọn) Sau khi ok: *"Publish bài 42"* → agent gọi `publish_post(confirmed=true)`.

**Nguyên tắc:** Luôn draft trước; publish chỉ khi bạn nói rõ.

---

## Post Agent API (đã implement token-based)

### 1. Post Agent API (Laravel)

API riêng, xác thực **Bearer token** (không dùng cookie admin). Token cấu hình qua `POST_AGENT_API_TOKEN`.

```
GET    /api/agent/posts
GET    /api/agent/posts/{id}
POST   /api/agent/posts/draft       → luôn tạo draft (is_published=false)
PUT    /api/agent/posts/{id}
POST   /api/agent/posts/{id}/publish → body: { "confirmed": true }
```

Validation tái dùng `CreatePostRequest` / `UpdatePostRequest`. Markdown theo `resources/ts/utils/postMarkdown.ts`:

```markdown
# Tiêu đề

Tags: tag1, tag2

> Mô tả ngắn

Nội dung body...
```

Hỗ trợ `en` + `vi` qua `post_translations`.

**Cấu hình global (skill + env, không cần .env từng project):** xem [post-agent-global-setup.md](./post-agent-global-setup.md).

### 2. MCP server (`packages/mcp-nvnhan-blog/`)

Chạy **stdio** — Cursor spawn process, giao tiếp qua stdin/stdout.

| Tool | Việc làm |
|------|----------|
| `list_posts` | Liệt kê draft / published |
| `get_post` | Lấy bài theo id hoặc slug |
| `create_post_draft` | Tạo bài mới (draft) |
| `update_post` | Sửa nội dung, tags, series |
| `preview_post_url` | Trả URL admin edit |
| `publish_post` | Publish — bắt buộc `confirmed: true` |

### 3. Cấu hình Cursor (`.cursor/mcp.json`)

```json
{
  "mcpServers": {
    "nvnhan-blog": {
      "command": "node",
      "args": ["packages/mcp-nvnhan-blog/dist/index.js"],
      "env": {
        "BLOG_API_URL": "https://nvnhan0810.com",
        "BLOG_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

- `BLOG_API_TOKEN` — token máy-máy, **không commit** (chỉ env local hoặc secret).
- Prod: tạo token scoped (`posts:read`, `posts:write`, `posts:publish`).

### 4. Skill (tuỳ chọn)

`.cursor/skills/publish-post-from-session/SKILL.md` — nhắc agent:

- Thu thập context từ session (file, commit, ghi chú).
- Luôn `create_post_draft`, trả link verify.
- Không `publish_post` trừ khi user yêu cầu rõ.

---

## MCP local vs Chat admin

| | Chat admin (đã có) | MCP local (kế hoạch) |
|--|-------------------|----------------------|
| Chạy ở đâu | Trình duyệt + server | Cursor IDE trên máy |
| Auth | Google OAuth session | API token |
| Context | Nội dung form hiện tại | Repo local, commit, file |
| Cursor | Cloud API (`CURSOR_API_KEY` server) | Agent local + MCP tools |
| Lưu DB | Bạn bấm **Lưu** | API ghi draft; publish qua tool hoặc admin |

Hai luồng **không thay nhau** — chat tiện khi đang sửa trên web; MCP tiện khi vừa xong việc ở local muốn publish nhanh.

---

## Bảo mật (MCP)

- Token chỉ trên máy dev / secret manager — không vào git.
- Mặc định mọi bài tạo qua API là **draft**.
- `publish_post` cần `confirmed: true` + nên verify trên admin trước.
- Rate limit trên `/api/agent/*`.

---

## Thứ tự implement đề xuất

1. Post Agent API + token auth + migration `agent_api_tokens`
2. Package MCP + `.cursor/mcp.json` example
3. Skill workflow
4. Test: agent local → draft → mở admin → Lưu / publish

---

## Code liên quan (đã có — chat admin)

| File | Vai trò |
|------|---------|
| `app/Domains/PostAgent/` | Service, Cursor client, parser |
| `app/Http/Controllers/Admin/PostAgentController.php` | `/admin/post-agent/*` |
| `resources/ts/components/posts/PostAgentChat.tsx` | UI chat |
| `config/post-agent.php` | `CURSOR_API_KEY`, `POST_AGENT_TIMEOUT` |
| `database/migrations/2026_07_02_000001_create_post_agent_sessions_table.php` | Lịch sử chat |

---

## Deploy (chat admin)

- Traefik/nginx/PHP-FPM timeout ≥ 150s (request sync ~120s).
- `POST_AGENT_TIMEOUT=120`, `CURSOR_API_KEY` trong secret prod.
- Cache database (cancel giữa chừng) — đã OK trên k3s.
