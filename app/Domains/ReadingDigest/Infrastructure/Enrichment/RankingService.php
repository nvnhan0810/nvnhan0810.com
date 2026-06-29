<?php

namespace App\Domains\ReadingDigest\Infrastructure\Enrichment;

use App\Domains\ReadingDigest\Infrastructure\Llm\CursorApiClient;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use Illuminate\Support\Str;

class RankingService
{
    public function __construct(
        private readonly CursorApiClient $cursorApi,
    ) {}

    /**
     * @param  array<int, array{article: DigestArticleModel, score: float}>  $candidates
     * @return array<int, array{article_id: string, score: float, reason: string}>
     */
    public function rank(array $candidates, array $profilePreferences, int $limit): array
    {
        if (count($candidates) < 5) {
            return $this->retrievalFallback($candidates, $limit, 'Recent articles from your sources');
        }

        if (! $this->cursorApi->isConfigured()) {
            return $this->retrievalFallback($candidates, $limit, 'Retrieval ranking (LLM disabled)');
        }

        $summaries = collect($candidates)->map(function ($item) {
            $article = $item['article'];
            $meta = $article->metadata ?? [];

            return [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => Str::limit($article->summary ?? '', 200),
                'type' => $meta['article_type'] ?? null,
                'retrieval_score' => $item['score'],
            ];
        })->values()->all();

        try {
            $response = $this->cursorApi->chatCompletions([
                'model' => config('reading-digest.ranking_model', 'composer-2.5'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Rank articles for a technical reader. Return JSON: {"rankings":[{"article_id":"...","score":0-100,"reason":"..."}]} sorted by score desc.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'preferences' => $profilePreferences,
                            'candidates' => $summaries,
                            'limit' => $limit,
                        ]),
                    ],
                ],
            ], 90);

            if (! $response->successful()) {
                throw new \RuntimeException('Ranking API failed');
            }

            $content = json_decode($response->json('choices.0.message.content'), true);
            $rankings = $this->normalizeRankings($content['rankings'] ?? [], $candidates, $limit);

            if ($rankings !== []) {
                return $rankings;
            }

            throw new \RuntimeException('Ranking API returned no valid items');
        } catch (\Throwable) {
            return $this->retrievalFallback($candidates, $limit, 'Retrieval fallback ranking');
        }
    }

    /**
     * @param  array<int, array{article: DigestArticleModel, score: float}>  $candidates
     * @return array<int, array{article_id: string, score: float, reason: string}>
     */
    private function retrievalFallback(array $candidates, int $limit, string $reason): array
    {
        return collect($candidates)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn ($item, $index) => [
                'article_id' => $item['article']->id,
                'score' => $item['score'] ?: (100 - $index),
                'reason' => $reason,
            ])
            ->all();
    }

    /**
     * @param  array<int, array{article: DigestArticleModel, score: float}>  $candidates
     * @return array<int, array{article_id: string, score: float, reason: string}>
     */
    private function normalizeRankings(array $rankings, array $candidates, int $limit): array
    {
        $candidateIds = collect($candidates)->pluck('article.id')->all();

        return collect($rankings)
            ->filter(fn ($row) => in_array($row['article_id'] ?? '', $candidateIds, true))
            ->take($limit)
            ->values()
            ->map(fn ($row) => [
                'article_id' => $row['article_id'],
                'score' => (float) ($row['score'] ?? 0),
                'reason' => (string) ($row['reason'] ?? 'LLM ranking'),
            ])
            ->all();
    }
}
