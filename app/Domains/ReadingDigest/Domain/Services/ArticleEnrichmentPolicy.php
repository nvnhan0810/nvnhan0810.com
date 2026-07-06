<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use Carbon\Carbon;

class ArticleEnrichmentPolicy
{
    public static function shouldEnrich(DigestArticleModel $article): bool
    {
        if ($article->enriched_at !== null) {
            return false;
        }

        if (! config('reading-digest.enrich_only_fetched_today', true)) {
            return true;
        }

        if ($article->fetched_at === null) {
            return false;
        }

        $timezone = (string) config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');

        return Carbon::parse($article->fetched_at)->timezone($timezone)->isToday();
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
