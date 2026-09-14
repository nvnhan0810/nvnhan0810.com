<?php

namespace Modules\ReadingDigest\Domain\Services;

use Carbon\Carbon;

class ArticleRetentionPolicy
{
    public static function retentionDays(): int
    {
        return max(1, (int) config('reading-digest.content_retention_days', 30));
    }

    public static function cutoff(?\DateTimeInterface $now = null): Carbon
    {
        return Carbon::parse($now ?? now())->subDays(self::retentionDays());
    }
}
