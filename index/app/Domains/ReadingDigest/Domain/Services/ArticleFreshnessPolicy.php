<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ArticleFreshnessPolicy
{
    public static function timezone(): string
    {
        return (string) config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');
    }

    public static function onlyFetchedToday(): bool
    {
        return filter_var(config('reading-digest.only_fetched_today', true), FILTER_VALIDATE_BOOL);
    }

    public static function isFetchedToday(?\DateTimeInterface $fetchedAt): bool
    {
        if ($fetchedAt === null) {
            return false;
        }

        return Carbon::parse($fetchedAt)->timezone(self::timezone())->isToday();
    }

    public static function isEligible(DigestArticleModel $article): bool
    {
        if (! self::onlyFetchedToday()) {
            return true;
        }

        return self::isFetchedToday($article->fetched_at);
    }

    /**
     * @param  Builder<DigestArticleModel>  $query
     * @return Builder<DigestArticleModel>
     */
    public static function applyScope(Builder $query): Builder
    {
        if (! self::onlyFetchedToday()) {
            return $query;
        }

        $tz = self::timezone();
        $start = Carbon::now($tz)->startOfDay();
        $end = Carbon::now($tz)->endOfDay();

        return $query->whereBetween('fetched_at', [$start, $end]);
    }
}
