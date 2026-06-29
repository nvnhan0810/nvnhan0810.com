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
            return collect($candidates)
                ->take($limit)
                ->values()
                ->map(fn ($item, $index) => [
                    'article_id' => $item['article']->id,
                    'score' => 100 - $index,
                    'reason' => 'High retrieval score',
                ])
                ->all();
        }

        if (! $this->cursorApi->isConfigured()) {
            return collect($candidates)
                ->sortByDesc('score')
                ->take($limit)
                ->values()
                ->map(fn ($item) => [
                    'article_id' => $item['article']->id,
                    'score' => $item['score'],
                    'reason' => 'Retrieval ranking (Cursor API disabled)',
                ])
                ->all();
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
            $rankings = $content['rankings'] ?? [];

            return collect($rankings)->take($limit)->values()->all();
        } catch (\Throwable) {
            return collect($candidates)
                ->sortByDesc('score')
                ->take($limit)
                ->values()
                ->map(fn ($item) => [
                    'article_id' => $item['article']->id,
                    'score' => $item['score'],
                    'reason' => 'Retrieval fallback ranking',
                ])
                ->all();
        }
    }
}
