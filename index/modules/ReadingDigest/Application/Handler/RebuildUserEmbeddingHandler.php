<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdArticleInteraction;
use App\Models\RdUserReadingProfile;

class RebuildUserEmbeddingHandler
{
    public function handle(int $userId): void
    {
        $positiveEvents = ['saved', 'finished_reading', 'liked'];
        $negativeEvents = ['dismissed', 'disliked'];

        $positiveArticleIds = RdArticleInteraction::query()
            ->where('user_id', $userId)
            ->whereIn('event', $positiveEvents)
            ->pluck('article_id');

        $negativeArticleIds = RdArticleInteraction::query()
            ->where('user_id', $userId)
            ->whereIn('event', $negativeEvents)
            ->pluck('article_id');

        $articles = RdArticle::query()
            ->with('embedding')
            ->whereIn('id', $positiveArticleIds)
            ->get()
            ->filter(fn ($a) => $a->embedding?->vector);

        if ($articles->isEmpty()) {
            return;
        }

        /** @var list<float> $firstVector */
        $firstVector = $articles->first()->embedding->vector;
        $dimensions = count($firstVector);
        if ($dimensions === 0) {
            return;
        }

        $sum = array_fill(0, $dimensions, 0.0);
        $count = 0;

        foreach ($articles as $article) {
            $vector = $article->embedding->vector;
            if (! is_array($vector) || count($vector) !== $dimensions) {
                continue;
            }

            $weight = in_array($article->id, $negativeArticleIds->all(), true) ? -1 : 1;

            for ($i = 0; $i < $dimensions; $i++) {
                $sum[$i] += $vector[$i] * $weight;
            }
            $count += $weight;
        }

        if ($count <= 0) {
            return;
        }

        $average = array_map(fn ($v) => $v / $count, $sum);

        RdUserReadingProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'user_embedding' => $average,
                'embedding_updated_at' => now(),
            ]
        );
    }
}
