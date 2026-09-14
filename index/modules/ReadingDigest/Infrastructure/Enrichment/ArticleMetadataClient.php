<?php

namespace Modules\ReadingDigest\Infrastructure\Enrichment;

use Illuminate\Support\Facades\Log;
use Modules\ReadingDigest\Infrastructure\Ai\AiApiClient;

class ArticleMetadataClient
{
    public function __construct(
        private readonly AiApiClient $ai = new AiApiClient,
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

        if (! $this->ai->isConfigured()) {
            return $this->fallbackBatch($articles);
        }

        $byId = [];

        foreach ($articles as $article) {
            $id = $article['id'];
            $text = $this->buildText($article);

            if ($text === '') {
                $byId[$id] = $this->fallbackMetadata();

                continue;
            }

            try {
                $response = $this->ai->enrich($text);

                if (! $response->successful()) {
                    Log::warning('AI enrichment failed', [
                        'article_id' => $id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $byId[$id] = $this->fallbackMetadata();

                    continue;
                }

                $byId[$id] = $this->normalizeFromAi(
                    (string) ($response->json('summary') ?? ''),
                    is_array($response->json('tags')) ? $response->json('tags') : [],
                );
            } catch (\Throwable $e) {
                Log::warning('AI enrichment error', [
                    'article_id' => $id,
                    'message' => $e->getMessage(),
                ]);
                $byId[$id] = $this->fallbackMetadata();
            }
        }

        return $byId;
    }

    /**
     * @param  array{id: string, title: string, summary?: ?string, content_text?: ?string}  $article
     */
    private function buildText(array $article): string
    {
        return trim(implode("\n\n", array_filter([
            $article['title'] ?? null,
            $article['summary'] ?? null,
            mb_substr((string) ($article['content_text'] ?? ''), 0, 4000),
        ], fn ($part) => is_string($part) && trim($part) !== '')));
    }

    /**
     * @param  list<mixed>  $tags
     * @return array<string, mixed>
     */
    private function normalizeFromAi(string $summary, array $tags): array
    {
        $cleanTags = array_values(array_filter(array_map(
            static fn ($tag) => is_string($tag) ? trim($tag) : '',
            $tags,
        )));

        $meta = $this->fallbackMetadata();
        $meta['topics'] = $cleanTags;
        $meta['style_tags'] = $cleanTags;

        if ($summary !== '') {
            $meta['summary'] = $summary;
        }

        return $meta;
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
