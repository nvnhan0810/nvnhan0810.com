<?php

namespace Modules\ReadingDigest\Domain\Services;

use App\Models\RdArticle;
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

    public static function isPublishedInTheFuture(?\DateTimeInterface $publishedAt): bool
    {
        if ($publishedAt === null) {
            return false;
        }

        return Carbon::parse($publishedAt)->isFuture();
    }

    public static function isEligible(RdArticle $article): bool
    {
        if (self::isPublishedInTheFuture($article->published_at)) {
            return false;
        }

        if (! self::onlyFetchedToday()) {
            return true;
        }

        return self::isFetchedToday($article->fetched_at);
    }

    /**
     * @param  Builder<RdArticle>  $query
     * @return Builder<RdArticle>
     */
    public static function excludeFuturePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    /**
     * @param  Builder<RdArticle>  $query
     * @return Builder<RdArticle>
     */
    public static function applyScope(Builder $query): Builder
    {
        self::excludeFuturePublished($query);

        if (! self::onlyFetchedToday()) {
            return $query;
        }

        $tz = self::timezone();
        $start = Carbon::now($tz)->startOfDay();
        $end = Carbon::now($tz)->endOfDay();

        return $query->whereBetween('fetched_at', [$start, $end]);
    }
}
