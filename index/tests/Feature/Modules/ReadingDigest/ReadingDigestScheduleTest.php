<?php

namespace Tests\Feature\Modules\ReadingDigest;

use Tests\TestCase;

final class ReadingDigestScheduleTest extends TestCase
{
    public function test_it_should_schedule_maintenance_jobs(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('reading-digest:purge-stale')
            ->expectsOutputToContain('reading-digest:decay-interest')
            ->expectsOutputToContain('reading-digest:rebuild-embeddings')
            ->assertSuccessful();
    }
}
