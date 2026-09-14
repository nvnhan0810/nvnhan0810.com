<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\SendDigestHandler;

class SendDigestTelegramJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $digestRunId) {}

    public function handle(SendDigestHandler $handler): void
    {
        $handler->handle($this->digestRunId);
    }
}
