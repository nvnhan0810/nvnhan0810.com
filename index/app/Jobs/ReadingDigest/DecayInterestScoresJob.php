<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\DecayInterestScoresHandler;

class DecayInterestScoresJob implements ShouldQueue
{
    use Queueable;

    public function handle(DecayInterestScoresHandler $handler): void
    {
        $handler->handle();
    }
}
