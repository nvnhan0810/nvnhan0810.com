<?php

namespace Modules\ReadingDigest\Domain\Services;

use App\Models\RdArticle;

class ArticleEnrichmentPolicy
{
    public static function shouldEnrich(RdArticle $article): bool
    {
        if ($article->enriched_at !== null) {
            return false;
        }

        return ArticleFreshnessPolicy::isEligible($article);
    }

    /**
     * @param  array<int, string>  $articleIds
     * @return array<int, string>
     */
    public static function filterEligibleIds(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        return RdArticle::query()
            ->whereIn('id', $articleIds)
            ->whereNull('enriched_at')
            ->get()
            ->filter(fn (RdArticle $article) => self::shouldEnrich($article))
            ->pluck('id')
            ->values()
            ->all();
    }
}
