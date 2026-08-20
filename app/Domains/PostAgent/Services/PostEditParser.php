<?php

namespace App\Domains\PostAgent\Services;

class PostEditParser
{
    /**
     * @return array{reply: string, edits: array{markdown: ?string, source_url: ?string}}
     */
    public function parse(string $raw): array
    {
        $decoded = $this->extractJson($raw);

        if ($decoded === null) {
            return [
                'reply' => trim($raw),
                'edits' => [
                    'markdown' => null,
                    'source_url' => null,
                ],
            ];
        }

        $reply = trim((string) ($decoded['reply'] ?? $raw));
        $edits = $decoded['edits'] ?? [];

        return [
            'reply' => $reply !== '' ? $reply : trim($raw),
            'edits' => $this->normalizeEdits(is_array($edits) ? $edits : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $edits
     * @return array{markdown: ?string, source_url: ?string}
     */
    private function normalizeEdits(array $edits): array
    {
        $markdown = null;

        if (isset($edits['markdown'])) {
            $value = trim((string) $edits['markdown']);
            $markdown = $value !== '' ? $value : null;
        } elseif (isset($edits['locales']) && is_array($edits['locales'])) {
            // Backward-compatible with older dual-locale agent replies.
            foreach (['vi', 'en'] as $locale) {
                if (! isset($edits['locales'][$locale])) {
                    continue;
                }

                $value = trim((string) $edits['locales'][$locale]);
                if ($value !== '') {
                    $markdown = $value;
                    break;
                }
            }
        }

        $sourceUrl = null;

        if (array_key_exists('source_url', $edits)) {
            $url = trim((string) $edits['source_url']);
            $sourceUrl = $url !== '' ? $url : null;
        } elseif (isset($edits['source_urls']) && is_array($edits['source_urls'])) {
            foreach (['vi', 'en'] as $locale) {
                if (! isset($edits['source_urls'][$locale])) {
                    continue;
                }

                $url = trim((string) $edits['source_urls'][$locale]);
                if ($url !== '') {
                    $sourceUrl = $url;
                    break;
                }
            }
        }

        return [
            'markdown' => $markdown,
            'source_url' => $sourceUrl,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $trimmed = trim($raw);

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches)) {
            $decoded = json_decode($matches[1], true);

            return is_array($decoded) ? $decoded : null;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
