<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\RebuildUserEmbeddingHandler;

class RebuildUserEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?int $userId = null) {}

    public function handle(RebuildUserEmbeddingHandler $handler): void
    {
        if ($this->userId === null) {
            $handler->handleAll();

            return;
        }

        $handler->handle($this->userId);
    }
}
