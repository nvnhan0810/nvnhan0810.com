<?php

namespace App\Domains\PostAgent\Services;

use App\Models\Post;

class PostEditParser
{
    /**
     * @return array{reply: string, edits: array{locales: array<string, string>, source_urls: array<string, string>}}
     */
    public function parse(string $raw): array
    {
        $decoded = $this->extractJson($raw);

        if ($decoded === null) {
            return [
                'reply' => trim($raw),
                'edits' => [
                    'locales' => [],
                    'source_urls' => [],
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
     * @return array{locales: array<string, string>, source_urls: array<string, string>}
     */
    private function normalizeEdits(array $edits): array
    {
        $locales = [];
        $rawLocales = $edits['locales'] ?? [];

        if (is_array($rawLocales)) {
            foreach (Post::SUPPORTED_LOCALES as $locale) {
                if (! isset($rawLocales[$locale])) {
                    continue;
                }

                $markdown = trim((string) $rawLocales[$locale]);

                if ($markdown !== '') {
                    $locales[$locale] = $markdown;
                }
            }
        }

        $sourceUrls = [];
        $rawUrls = $edits['source_urls'] ?? [];

        if (is_array($rawUrls)) {
            foreach (Post::SUPPORTED_LOCALES as $locale) {
                if (! isset($rawUrls[$locale])) {
                    continue;
                }

                $url = trim((string) $rawUrls[$locale]);

                if ($url !== '') {
                    $sourceUrls[$locale] = $url;
                }
            }
        }

        return [
            'locales' => $locales,
            'source_urls' => $sourceUrls,
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
