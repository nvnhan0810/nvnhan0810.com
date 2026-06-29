<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;

class RetrievalScoringService
{
    /**
     * @param  array<string, float>  $favoriteTopics
     * @param  array<string, float>  $interestScores
     * @param  array<int, string>  $ignoredTaxonomyIds
     */
    public function score(
        DigestArticleModel $article,
        array $favoriteTopics,
        array $interestScores,
        array $ignoredTaxonomyIds,
        ?array $userEmbedding,
        ?array $articleEmbedding,
        array $preferredSources = [],
        ?string $preferredDifficulty = null,
        array $preferredArticleTypes = [],
        float $contextBoost = 0,
    ): float {
        if ($article->force_exclude) {
            return -999;
        }

        if ($article->force_include) {
            return 999;
        }

        $metadata = $article->metadata ?? [];
        $taxonomyIds = collect($article->taxonomyNodes)->pluck('id')->all();
        $taxonomyPaths = collect($article->taxonomyNodes)->pluck('path')->all();

        foreach ($taxonomyIds as $taxonomyId) {
            if (in_array($taxonomyId, $ignoredTaxonomyIds, true)) {
                return -999;
            }
        }

        $score = 0.0;

        foreach ($article->taxonomyNodes as $node) {
            $pathScore = $favoriteTopics[$node->path] ?? 0;
            $interest = $interestScores[$node->id] ?? 0;
            $confidence = (float) ($node->pivot->confidence ?? 1);
            $score += ($pathScore * 0.6 + $interest * 0.4) * $confidence;
        }

        if ($userEmbedding && $articleEmbedding) {
            $score += $this->cosineSimilarity($userEmbedding, $articleEmbedding) * 20;
        }

        if ($preferredDifficulty && ($metadata['difficulty'] ?? null) === $preferredDifficulty) {
            $score += 5;
        }

        $articleType = $metadata['article_type'] ?? null;
        if ($articleType && in_array($articleType, $preferredArticleTypes, true)) {
            $score += 8;
        }

        $handsOn = (float) ($metadata['hands_on_score'] ?? 0);
        $score += $handsOn * 10;

        if ($article->source && in_array($article->source->name, $preferredSources, true)) {
            $score += 3;
        }

        $score += $contextBoost;

        $negativeSignals = $metadata['negative_signals'] ?? [];
        $score -= count($negativeSignals) * 2;

        return round($score, 4);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
