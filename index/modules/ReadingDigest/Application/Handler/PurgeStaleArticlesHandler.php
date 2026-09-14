<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use Modules\ReadingDigest\Domain\Services\ArticleRetentionPolicy;

class PurgeStaleArticlesHandler
{
    public function handle(?int $retentionDays = null): int
    {
        $days = max(1, $retentionDays ?? ArticleRetentionPolicy::retentionDays());
        $cutoff = now()->subDays($days);

        return RdArticle::query()
            ->where('force_include', false)
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where(function ($q) use ($cutoff): void {
                        $q->whereNotNull('published_at')
                            ->where('published_at', '<', $cutoff);
                    })
                    ->orWhere(function ($q) use ($cutoff): void {
                        $q->whereNull('published_at')
                            ->where('fetched_at', '<', $cutoff);
                    });
            })
            ->whereDoesntHave('interactions')
            ->delete();
    }
}
