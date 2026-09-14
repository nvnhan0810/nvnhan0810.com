<?php

namespace Tests\Feature\Modules\ReadingDigest;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DigestScheduleTest extends TestCase
{
    public function test_it_should_register_daily_digest_job_on_schedule(): void
    {
        $exitCode = Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('reading-digest:daily', $output);
        $this->assertStringContainsString('0 7 * * *', $output);
    }
}
