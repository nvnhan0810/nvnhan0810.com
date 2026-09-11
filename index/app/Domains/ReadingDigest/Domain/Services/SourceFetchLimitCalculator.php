<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Collection;

class SourceFetchLimitCalculator
{
    /**
     * Per-source fetch limit from linked enabled subjects' demand.
     *
     * demand = sum(articles_per_digest of enabled linked subjects)
     *          (null articles_per_digest → config articles_per_subject)
     * limit  = clamp(demand * multiplier, min, max)
     *
     * Sources with no enabled subjects use min.
     */
    public static function forSource(SourceModel $source): int
    {
        $subjects = $source->relationLoaded('subjects')
            ? $source->subjects
            : $source->subjects()->get();

        return self::fromSubjects($subjects);
    }

    /**
     * @param  Collection<int, \App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SubjectModel>|iterable  $subjects
     */
    public static function fromSubjects(iterable $subjects): int
    {
        $fallbackPerSubject = (int) config('reading-digest.articles_per_subject', 5);
        $multiplier = (int) config('reading-digest.fetch_demand_multiplier', 3);
        $min = (int) config('reading-digest.fetch_limit_min', 5);
        $max = (int) config('reading-digest.fetch_limit_per_source', 15);

        $demand = 0;
        foreach ($subjects as $subject) {
            if (! ($subject->enabled ?? true)) {
                continue;
            }

            $demand += (int) ($subject->articles_per_digest ?? $fallbackPerSubject);
        }

        if ($demand <= 0) {
            return max(1, $min);
        }

        return max($min, min($max, $demand * $multiplier));
    }
}
