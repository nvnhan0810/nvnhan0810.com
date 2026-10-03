<?php

namespace App\Console\Commands\ReadingDigest;

use App\Jobs\ReadingDigest\DecayInterestScoresJob;
use Illuminate\Console\Command;

class DecayInterestScoresCommand extends Command
{
    protected $signature = 'reading-digest:decay-interest';

    protected $description = 'Dispatch DecayInterestScoresJob to the queue';

    public function handle(): int
    {
        DecayInterestScoresJob::dispatch();
        $this->info('Dispatched DecayInterestScoresJob.');

        return self::SUCCESS;
    }
}
