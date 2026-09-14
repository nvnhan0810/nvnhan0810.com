<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\PurgeStaleArticlesHandler;

class PurgeStaleArticlesJob implements ShouldQueue
{
    use Queueable;

    public function handle(PurgeStaleArticlesHandler $handler): void
    {
        $handler->handle();
    }
}
