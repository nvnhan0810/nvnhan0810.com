<?php

namespace App\Console\Commands\ReadingDigest;

use App\Jobs\ReadingDigest\RunDailyDigestJob;
use Illuminate\Console\Command;

class RunDailyDigestCommand extends Command
{
    protected $signature = 'reading-digest:run-daily';

    protected $description = 'Dispatch RunDailyDigestJob to the queue';

    public function handle(): int
    {
        RunDailyDigestJob::dispatch();
        $this->info('Dispatched RunDailyDigestJob.');

        return self::SUCCESS;
    }
}
