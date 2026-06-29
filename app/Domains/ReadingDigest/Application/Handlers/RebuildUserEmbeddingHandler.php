<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\ArticleInteractionModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;

class RebuildUserEmbeddingHandler
{
    public function handle(int $userId): void
    {
        $positiveEvents = ['saved', 'finished_reading', 'liked'];
        $negativeEvents = ['dismissed', 'disliked'];

        $positiveArticleIds = ArticleInteractionModel::query()
            ->where('user_id', $userId)
            ->whereIn('event', $positiveEvents)
            ->pluck('article_id');

        $negativeArticleIds = ArticleInteractionModel::query()
            ->where('user_id', $userId)
            ->whereIn('event', $negativeEvents)
            ->pluck('article_id');

        $articles = DigestArticleModel::query()
            ->with('embedding')
            ->whereIn('id', $positiveArticleIds)
            ->get()
            ->filter(fn ($a) => $a->embedding?->vector);

        if ($articles->isEmpty()) {
            return;
        }

        $dimensions = (int) config('reading-digest.embedding_dimensions', 1536);
        $sum = array_fill(0, $dimensions, 0.0);
        $count = 0;

        foreach ($articles as $article) {
            $vector = $article->embedding->vector;
            $weight = in_array($article->id, $negativeArticleIds->all(), true) ? -1 : 1;

            for ($i = 0; $i < min($dimensions, count($vector)); $i++) {
                $sum[$i] += $vector[$i] * $weight;
            }
            $count += $weight;
        }

        if ($count <= 0) {
            return;
        }

        $average = array_map(fn ($v) => $v / $count, $sum);

        UserReadingProfileModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'user_embedding' => $average,
                'embedding_updated_at' => now(),
            ]
        );
    }
}
