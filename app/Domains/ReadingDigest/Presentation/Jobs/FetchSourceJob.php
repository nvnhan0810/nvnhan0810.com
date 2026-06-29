<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\FetchSourceHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $sourceId) {}

    public function handle(FetchSourceHandler $handler): void
    {
        $handler->handle($this->sourceId);
    }
}
