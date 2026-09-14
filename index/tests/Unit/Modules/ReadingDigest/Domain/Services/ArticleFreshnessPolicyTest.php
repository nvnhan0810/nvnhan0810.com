<?php

namespace Tests\Unit\Modules\ReadingDigest\Domain\Services;

use Carbon\Carbon;
use Modules\ReadingDigest\Domain\Services\ArticleFreshnessPolicy;
use Tests\TestCase;

final class ArticleFreshnessPolicyTest extends TestCase
{
    public function test_it_should_detect_published_at_in_the_future(): void
    {
        $this->assertTrue(
            ArticleFreshnessPolicy::isPublishedInTheFuture(Carbon::now()->addDay())
        );
    }

    public function test_it_should_allow_published_at_in_the_past_or_null(): void
    {
        $this->assertFalse(
            ArticleFreshnessPolicy::isPublishedInTheFuture(Carbon::now()->subHour())
        );
        $this->assertFalse(
            ArticleFreshnessPolicy::isPublishedInTheFuture(null)
        );
    }
}
