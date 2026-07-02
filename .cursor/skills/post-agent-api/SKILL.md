---
name: post-agent-api
description: >-
  Tạo/cập nhật/publish bài viết lên nvnhan0810.com qua Post Agent API.
  Dùng khi user muốn tổng hợp bài từ session local và đẩy draft lên blog.
---

# Post Agent API — draft / update / publish

Agent gọi API blog qua **Bearer token**. Luôn tạo **draft** trước; chỉ publish khi user nói rõ.

## Cấu hình global (không cần .env từng project)

Đọc config từ file global trên máy user:

`~/.config/nvnhan-blog/agent.env`

Format:

```env
BLOG_API_URL=https://nvnhan0810.com
BLOG_API_TOKEN=your-token-here
```

Setup một lần:

```bash
mkdir -p ~/.config/nvnhan-blog
cp /path/to/nvnhan0810.com/docs/examples/agent.env.example ~/.config/nvnhan-blog/agent.env
chmod 600 ~/.config/nvnhan-blog/agent.env
# Sửa BLOG_API_TOKEN trong file vừa tạo
```

Tuỳ chọn — load vào mọi terminal (WSL/zsh):

```bash
# Thêm vào ~/.zshrc
[[ -f ~/.config/nvnhan-blog/agent.env ]] && set -a && source ~/.config/nvnhan-blog/agent.env && set +a
```

**Skill global:** copy thư mục skill này sang `~/.cursor/skills/post-agent-api/` để dùng ở mọi workspace.

**Lưu ý:** `POST_AGENT_API_TOKEN` trên **server/k3s** là biến riêng (secret prod). `BLOG_API_TOKEN` trên máy local phải **trùng giá trị** token đó.

## Load config trước khi gọi API

Luôn load global config (fallback env nếu đã export sẵn):

```python
import os
from pathlib import Path

def load_blog_agent_config() -> tuple[str, str]:
    config_path = Path.home() / ".config" / "nvnhan-blog" / "agent.env"

    if config_path.exists():
        for line in config_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            value = value.strip().strip('"').strip("'")
            if key and value:
                os.environ.setdefault(key, value)

    url = os.environ.get("BLOG_API_URL", "").rstrip("/")
    token = os.environ.get("BLOG_API_TOKEN", "").strip()

    if not url or not token:
        raise RuntimeError(
            "Thiếu BLOG_API_URL hoặc BLOG_API_TOKEN. "
            "Tạo ~/.config/nvnhan-blog/agent.env theo docs/examples/agent.env.example"
        )

    return url, token
```

## Endpoints

Base: `{BLOG_API_URL}/api/agent/posts`

| Method | Path | Mục đích |
|--------|------|----------|
| GET | `/api/agent/posts` | List posts |
| GET | `/api/agent/posts/{id}` | Chi tiết |
| POST | `/api/agent/posts/draft` | Tạo draft (`is_published=false`) |
| PUT | `/api/agent/posts/{id}` | Update |
| POST | `/api/agent/posts/{id}/publish` | Publish — body `{ "confirmed": true }` |

Header: `Authorization: Bearer {BLOG_API_TOKEN}`

## Nội dung EN + VI

Payload `translations` — mỗi locale:

```json
{
  "locale": "en",
  "title": "...",
  "description": "...",
  "content": "...",
  "source_url": null
}
```

Ít nhất một locale (en hoặc vi) phải có đủ `title` + `content`.

Markdown gợi ý khi soạn:

```md
# Title

Tags: tag1, tag2

> Description

Body...
```

`Tags:` chỉ cần trên locale `en`.

## Quy trình

1. Thu thập context từ session (file đổi, commit, ghi chú user).
2. Soạn EN + VI (trừ khi user chỉ yêu cầu một locale).
3. `POST .../draft` với `is_published: false`.
4. Trả `id`, `edit_url`, tóm tắt nội dung.
5. **Không** publish trừ khi user nói rõ "publish / đăng bài".

## Ví dụ tạo draft

```python
import json
import datetime
import urllib.request

BLOG_API_URL, BLOG_API_TOKEN = load_blog_agent_config()

payload = {
    "translations": {
        "en": {
            "locale": "en",
            "title": "Example title",
            "description": "Short description",
            "content": "Body in English",
            "source_url": None,
        },
        "vi": {
            "locale": "vi",
            "title": "Tiêu đề ví dụ",
            "description": "Mô tả ngắn",
            "content": "Nội dung tiếng Việt",
            "source_url": None,
        },
    },
    "tags": ["example"],
    "series_ids": [],
    "published_at": datetime.date.today().isoformat(),
    "is_published": False,
}

req = urllib.request.Request(
    f"{BLOG_API_URL}/api/agent/posts/draft",
    data=json.dumps(payload).encode("utf-8"),
    headers={
        "Content-Type": "application/json",
        "Authorization": f"Bearer {BLOG_API_TOKEN}",
    },
    method="POST",
)

with urllib.request.urlopen(req, timeout=60) as resp:
    print(resp.read().decode("utf-8"))
```
