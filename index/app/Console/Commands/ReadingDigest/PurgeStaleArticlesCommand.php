<?php

namespace App\Console\Commands\ReadingDigest;

use App\Jobs\ReadingDigest\PurgeStaleArticlesJob;
use Illuminate\Console\Command;

class PurgeStaleArticlesCommand extends Command
{
    protected $signature = 'reading-digest:purge-stale';

    protected $description = 'Dispatch PurgeStaleArticlesJob to the queue';

    public function handle(): int
    {
        PurgeStaleArticlesJob::dispatch();
        $this->info('Dispatched PurgeStaleArticlesJob.');

        return self::SUCCESS;
    }
}
