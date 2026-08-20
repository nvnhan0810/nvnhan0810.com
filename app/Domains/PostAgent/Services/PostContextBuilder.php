<?php

namespace App\Domains\PostAgent\Services;

class PostContextBuilder
{
    /**
     * @param  array{doc?: string, source_url?: string|null}  $context
     */
    public function buildSystemPrompt(array $context): string
    {
        return <<<PROMPT
Bạn là trợ lý biên tập bài viết blog cho admin nvnhan0810.com.

## Nhiệm vụ
- Trả lời bằng tiếng Việt (trừ khi user yêu cầu ngôn ngữ khác).
- Chỉnh sửa nội dung bài viết theo yêu cầu user.
- Nội dung bài luôn viết bằng tiếng Việt.

## Định dạng markdown
Document markdown độc lập với cấu trúc:
```
# Tiêu đề

Tags: tag1,tag2

> Mô tả ngắn (tùy chọn)

Nội dung body (markdown)...
```

- Dòng đầu: `# ` + title (bắt buộc nếu có nội dung).
- `Tags:` dùng chung cho bài.
- `> ` là description.
- Phần còn lại là body.

## Cách trả lời
LUÔN trả về JSON hợp lệ (không bọc trong markdown code fence), đúng schema:
{
  "reply": "Giải thích ngắn gọn những gì bạn đã làm hoặc trả lời câu hỏi",
  "edits": {
    "markdown": "toàn bộ markdown đã sửa (chỉ gửi khi có thay đổi nội dung)",
    "source_url": "https://..."
  }
}

- Nếu chỉ trả lời câu hỏi, không sửa nội dung: bỏ trường "edits" hoặc để "edits" rỗng.
- Khi sửa nội dung, gửi TOÀN BỘ markdown (không chỉ phần diff).
- "source_url" chỉ gửi khi cần đổi URL nguồn.

## Ngữ cảnh hiện tại
{$this->formatCurrentDoc($context)}
{$this->formatSourceUrl($context)}
PROMPT;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array{doc?: string, source_url?: string|null}  $context
     */
    public function buildPrompt(string $userMessage, array $context, array $history = []): string
    {
        $parts = [$this->buildSystemPrompt($context)];

        foreach ($history as $message) {
            $role = strtoupper((string) ($message['role'] ?? 'user'));
            $parts[] = "[{$role}]\n".(string) ($message['content'] ?? '');
        }

        $parts[] = "[USER]\n{$userMessage}";

        return implode("\n\n", $parts);
    }

    /**
     * @param  array{doc?: string, source_url?: string|null}  $context
     */
    private function formatCurrentDoc(array $context): string
    {
        $content = trim((string) ($context['doc'] ?? ''));

        if ($content === '') {
            return "### Nội dung hiện tại\n(trống)";
        }

        return "### Nội dung hiện tại\n{$content}";
    }

    /**
     * @param  array{doc?: string, source_url?: string|null}  $context
     */
    private function formatSourceUrl(array $context): string
    {
        $url = trim((string) ($context['source_url'] ?? ''));

        return '### Source URL\n'.($url !== '' ? $url : '(trống)');
    }
}
