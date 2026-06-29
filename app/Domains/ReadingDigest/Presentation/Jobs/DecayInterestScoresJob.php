<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\DecayInterestScoresHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DecayInterestScoresJob implements ShouldQueue
{
    use Queueable;

    public function handle(DecayInterestScoresHandler $handler): void
    {
        $handler->handle();
    }
}
