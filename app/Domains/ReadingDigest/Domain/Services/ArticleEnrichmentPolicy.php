<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;

class ArticleEnrichmentPolicy
{
    public static function shouldEnrich(DigestArticleModel $article): bool
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

        return DigestArticleModel::query()
            ->whereIn('id', $articleIds)
            ->whereNull('enriched_at')
            ->get()
            ->filter(fn (DigestArticleModel $article) => self::shouldEnrich($article))
            ->pluck('id')
            ->values()
            ->all();
    }
}
