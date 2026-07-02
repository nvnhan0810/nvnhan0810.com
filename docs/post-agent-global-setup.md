# Post Agent — Cấu hình global (skill + env)

Dùng **một lần setup** cho mọi workspace Cursor, không cần thêm biến vào `.env` từng project.

---

## Phân biệt server vs máy local

| Biến | Ở đâu | Mục đích |
|------|--------|----------|
| `POST_AGENT_API_TOKEN` | Server k3s / `secret.env` | Laravel verify request tới `/api/agent/*` |
| `BLOG_API_URL` + `BLOG_API_TOKEN` | Máy bạn (`~/.config/...`) | Cursor agent gọi API |

Hai token **cùng giá trị** — server giữ bản prod, máy local giữ bản để agent dùng.

---

## 1. Skill global

Copy skill từ repo sang thư mục user (dùng mọi project):

```bash
mkdir -p ~/.cursor/skills
cp -r /path/to/nvnhan0810.com/.cursor/skills/post-agent-api ~/.cursor/skills/
```

Cursor load skill từ `~/.cursor/skills/` cho tất cả workspace.

> Không đặt skill tùy chỉnh trong `~/.cursor/skills-cursor/` — thư mục đó dành cho skill built-in của Cursor.

---

## 2. Env global (file config)

```bash
mkdir -p ~/.config/nvnhan-blog
cp /path/to/nvnhan0810.com/docs/examples/agent.env.example ~/.config/nvnhan-blog/agent.env
chmod 600 ~/.config/nvnhan-blog/agent.env
```

Sửa `~/.config/nvnhan-blog/agent.env`:

```env
BLOG_API_URL=https://nvnhan0810.com
BLOG_API_TOKEN=<cùng giá trị POST_AGENT_API_TOKEN trên server>
```

### Tuỳ chọn: tự load trong mọi terminal

Thêm vào `~/.zshrc`:

```bash
[[ -f ~/.config/nvnhan-blog/agent.env ]] && set -a && source ~/.config/nvnhan-blog/agent.env && set +a
```

Skill vẫn đọc trực tiếp file `agent.env` nếu env chưa export — không bắt buộc bước này.

---

## 3. Cách khác (nếu cần)

| Cách | Khi nào dùng |
|------|----------------|
| `~/.config/nvnhan-blog/agent.env` | **Khuyến nghị** — một file, skill đọc được |
| `export` trong `~/.zshrc` | Terminal / agent inherit env |
| `~/.cursor/mcp.json` env block | Chỉ khi dùng MCP server (không phải skill) |

Cursor **không có** file “global .env” riêng cho agent — pattern file config + skill là cách ổn định nhất.

---

## 4. Kiểm tra nhanh

```bash
# Đã có file config
test -f ~/.config/nvnhan-blog/agent.env && echo OK

# Gọi API thử (thay token thật)
source ~/.config/nvnhan-blog/agent.env
curl -s -H "Authorization: Bearer $BLOG_API_TOKEN" \
  "$BLOG_API_URL/api/agent/posts?is_published=false" | head
```

Trong Cursor: *"Tạo draft bài test ngắn EN+VI lên blog"* — agent phải trả `edit_url`.
