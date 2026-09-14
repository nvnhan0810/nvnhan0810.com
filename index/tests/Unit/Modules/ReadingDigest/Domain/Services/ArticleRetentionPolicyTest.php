<?php

namespace Tests\Unit\Modules\ReadingDigest\Domain\Services;

use Carbon\Carbon;
use Modules\ReadingDigest\Domain\Services\ArticleRetentionPolicy;
use Tests\TestCase;

final class ArticleRetentionPolicyTest extends TestCase
{
    public function test_it_should_use_configured_retention_days_with_floor_of_one(): void
    {
        config(['reading-digest.content_retention_days' => 30]);
        $this->assertSame(30, ArticleRetentionPolicy::retentionDays());

        config(['reading-digest.content_retention_days' => 0]);
        $this->assertSame(1, ArticleRetentionPolicy::retentionDays());
    }

    public function test_it_should_compute_cutoff_from_retention_days(): void
    {
        config(['reading-digest.content_retention_days' => 30]);
        $now = Carbon::parse('2026-09-14 12:00:00');

        $this->assertTrue(
            ArticleRetentionPolicy::cutoff($now)->equalTo(Carbon::parse('2026-08-15 12:00:00'))
        );
    }
}
