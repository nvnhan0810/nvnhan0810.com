<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\SendDigestHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDigestTelegramJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $digestRunId) {}

    public function handle(SendDigestHandler $handler): void
    {
        $handler->handle($this->digestRunId);
    }
}
