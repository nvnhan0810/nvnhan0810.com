<?php

namespace App\Domains\ReadingDigest\Infrastructure\Enrichment;

use App\Domains\ReadingDigest\Infrastructure\Llm\GeminiApiClient;
use Illuminate\Support\Facades\Log;

class ArticleMetadataClient
{
    private const SYSTEM_PROMPT = 'Extract article metadata. For a single article return JSON with keys: topics (array), frameworks (array), article_type, difficulty (beginner|intermediate|advanced), freshness (news|evergreen), hands_on_score (0-1), style_tags (array), negative_signals (array), taxonomy_paths (array of dot paths like programming.frontend.react). For multiple articles return JSON: {"articles":[{"article_id":"...","topics":[],"frameworks":[],"article_type":"...","difficulty":"...","freshness":"...","hands_on_score":0,"style_tags":[],"negative_signals":[],"taxonomy_paths":[]}]}';

    public function __construct(
        private readonly GeminiApiClient $gemini,
    ) {}

    public function enrich(string $title, ?string $summary, ?string $contentText): array
    {
        $results = $this->enrichBatch([
            [
                'id' => 'single',
                'title' => $title,
                'summary' => $summary,
                'content_text' => $contentText,
            ],
        ]);

        return $results['single'] ?? $this->fallbackMetadata();
    }

    /**
     * @param  array<int, array{id: string, title: string, summary?: ?string, content_text?: ?string}>  $articles
     * @return array<string, array<string, mixed>>
     */
    public function enrichBatch(array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        if (! $this->gemini->isConfigured()) {
            return collect($articles)
                ->mapWithKeys(fn (array $article) => [$article['id'] => $this->fallbackMetadata()])
                ->all();
        }

        $payloadArticles = collect($articles)->map(fn (array $article) => [
            'article_id' => $article['id'],
            'title' => $article['title'],
            'summary' => $article['summary'],
            'excerpt' => mb_substr($article['content_text'] ?? '', 0, 800),
        ])->values()->all();

        try {
            $response = $this->gemini->chatCompletions([
                'model' => config('reading-digest.enrichment_model', 'gemini-2.5-flash'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => json_encode(['articles' => $payloadArticles], JSON_UNESCAPED_UNICODE)],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini batch enrichment failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fallbackBatch($articles);
            }

            $decoded = json_decode((string) $response->json('choices.0.message.content'), true);
            if (! is_array($decoded)) {
                return $this->fallbackBatch($articles);
            }

            if (count($articles) === 1 && ! isset($decoded['articles'])) {
                return ['single' => $this->normalizeMetadata($decoded)];
            }

            $byId = [];
            foreach ($decoded['articles'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $articleId = (string) ($row['article_id'] ?? '');
                if ($articleId === '') {
                    continue;
                }

                $byId[$articleId] = $this->normalizeMetadata($row);
            }

            foreach ($articles as $article) {
                $id = $article['id'];
                $byId[$id] ??= $this->fallbackMetadata();
            }

            return $byId;
        } catch (\Throwable $e) {
            Log::warning('Gemini batch enrichment error', ['message' => $e->getMessage()]);

            return $this->fallbackBatch($articles);
        }
    }

    /**
     * @param  array<int, array{id: string, title: string, summary?: ?string, content_text?: ?string}>  $articles
     * @return array<string, array<string, mixed>>
     */
    private function fallbackBatch(array $articles): array
    {
        return collect($articles)
            ->mapWithKeys(fn (array $article) => [$article['id'] => $this->fallbackMetadata()])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeMetadata(array $row): array
    {
        unset($row['article_id']);

        return array_merge($this->fallbackMetadata(), array_intersect_key($row, array_flip([
            'topics',
            'frameworks',
            'article_type',
            'difficulty',
            'freshness',
            'hands_on_score',
            'style_tags',
            'negative_signals',
            'taxonomy_paths',
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackMetadata(): array
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
