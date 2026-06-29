<?php

namespace App\Domains\ReadingDigest\Infrastructure\Enrichment;

use App\Domains\ReadingDigest\Infrastructure\Llm\CursorApiClient;
use Illuminate\Support\Facades\Log;

class OpenAiMetadataClient
{
    public function __construct(
        private readonly CursorApiClient $cursorApi,
    ) {}

    public function enrich(string $title, ?string $summary, ?string $contentText): array
    {
        if (! $this->cursorApi->isConfigured()) {
            return $this->fallbackMetadata($title, $summary);
        }

        $text = trim(implode("\n\n", array_filter([$title, $summary, mb_substr($contentText ?? '', 0, 2000)])));

        try {
            $response = $this->cursorApi->chatCompletions([
                'model' => config('reading-digest.ranking_model', 'composer-2.5'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extract article metadata as JSON with keys: topics (array), frameworks (array), article_type, difficulty (beginner|intermediate|advanced), freshness (news|evergreen), hands_on_score (0-1), style_tags (array), negative_signals (array), taxonomy_paths (array of dot paths like programming.frontend.react).',
                    ],
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            if (! $response->successful()) {
                if ($response->status() !== 404) {
                    Log::warning('LLM enrichment failed', [
                        'status' => $response->status(),
                        'endpoint' => $this->cursorApi->baseUrl().'/chat/completions',
                    ]);
                }

                return $this->fallbackMetadata($title, $summary);
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : $this->fallbackMetadata($title, $summary);
        } catch (\Throwable $e) {
            Log::warning('LLM enrichment error', ['message' => $e->getMessage()]);

            return $this->fallbackMetadata($title, $summary);
        }
    }

    private function fallbackMetadata(string $title, ?string $summary): array
    {
        return [
            'topics' => [],
            'frameworks' => [],
            'article_type' => 'news',
            'difficulty' => 'intermediate',
            'freshness' => 'news',
            'hands_on_score' => 0.3,
            'style_tags' => [],
            'negative_signals' => [],
            'taxonomy_paths' => [],
        ];
    }
}
