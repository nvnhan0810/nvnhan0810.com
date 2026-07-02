<?php

namespace App\Domains\PostAgent\Services;

use App\Models\Post;

class PostContextBuilder
{
    /**
     * @param  array{docs: array<string, string>, source_urls?: array<string, string>, active_locale?: string}  $context
     */
    public function buildSystemPrompt(array $context): string
    {
        $locales = Post::SUPPORTED_LOCALES;
        $localeList = implode(', ', $locales);

        return <<<PROMPT
Bạn là trợ lý biên tập bài viết blog cho admin nvnhan0810.com.

## Nhiệm vụ
- Trả lời bằng tiếng Việt (trừ khi user yêu cầu ngôn ngữ khác).
- Chỉnh sửa nội dung bài viết theo yêu cầu user.
- Hỗ trợ đồng thời các locale: {$localeList}.

## Định dạng markdown mỗi locale
Mỗi locale là một document markdown độc lập với cấu trúc:
```
# Tiêu đề

Tags: tag1,tag2

> Mô tả ngắn (tùy chọn)

Nội dung body (markdown)...
```

- Dòng đầu: `# ` + title (bắt buộc nếu locale có nội dung).
- `Tags:` chỉ cần trên locale en (tags dùng chung cho bài).
- `> ` là description.
- Phần còn lại là body.

## Quy tắc đa ngôn ngữ
- Khi user yêu cầu viết/dịch cả hai ngôn ngữ, cập nhật cả en và vi.
- Khi user chỉ nhắc một locale, chỉ sửa locale đó; giữ nguyên locale còn lại trừ khi user yêu cầu.
- Nội dung en và vi nên tương đương về ý, không dịch word-by-word máy móc.

## Cách trả lời
LUÔN trả về JSON hợp lệ (không bọc trong markdown code fence), đúng schema:
{
  "reply": "Giải thích ngắn gọn những gì bạn đã làm hoặc trả lời câu hỏi",
  "edits": {
    "locales": {
      "en": "toàn bộ markdown locale en (chỉ gửi locale đã thay đổi)",
      "vi": "toàn bộ markdown locale vi (chỉ gửi locale đã thay đổi)"
    },
    "source_urls": {
      "en": "https://...",
      "vi": "https://..."
    }
  }
}

- Nếu chỉ trả lời câu hỏi, không sửa nội dung: bỏ trường "edits" hoặc để "edits" rỗng.
- Khi sửa nội dung, gửi TOÀN BỘ markdown của locale đã sửa (không chỉ phần diff).
- "source_urls" chỉ gửi khi cần đổi URL nguồn.

## Ngữ cảnh hiện tại
Locale đang active trên form: {$this->activeLocale($context)}
{$this->formatCurrentDocs($context)}
{$this->formatSourceUrls($context)}
PROMPT;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
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
     * @param  array{docs: array<string, string>, source_urls?: array<string, string>, active_locale?: string}  $context
     */
    private function activeLocale(array $context): string
    {
        $locale = (string) ($context['active_locale'] ?? Post::DEFAULT_LOCALE);

        return in_array($locale, Post::SUPPORTED_LOCALES, true) ? $locale : Post::DEFAULT_LOCALE;
    }

    /**
     * @param  array{docs: array<string, string>, source_urls?: array<string, string>, active_locale?: string}  $context
     */
    private function formatCurrentDocs(array $context): string
    {
        $docs = $context['docs'] ?? [];
        $sections = [];

        foreach (Post::SUPPORTED_LOCALES as $locale) {
            $content = trim((string) ($docs[$locale] ?? ''));

            if ($content === '') {
                $sections[] = "### Locale {$locale}\n(trống)";
            } else {
                $sections[] = "### Locale {$locale}\n{$content}";
            }
        }

        return "### Nội dung hiện tại\n".implode("\n\n", $sections);
    }

    /**
     * @param  array{docs: array<string, string>, source_urls?: array<string, string>, active_locale?: string}  $context
     */
    private function formatSourceUrls(array $context): string
    {
        $urls = $context['source_urls'] ?? [];
        $lines = [];

        foreach (Post::SUPPORTED_LOCALES as $locale) {
            $url = trim((string) ($urls[$locale] ?? ''));
            $lines[] = "- {$locale}: ".($url !== '' ? $url : '(trống)');
        }

        return "### Source URL\n".implode("\n", $lines);
    }
}
