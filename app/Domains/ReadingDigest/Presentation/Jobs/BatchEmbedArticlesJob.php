<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\BatchEmbedArticlesHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BatchEmbedArticlesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $articleIds
     */
    public function __construct(public readonly array $articleIds) {}

    public function handle(BatchEmbedArticlesHandler $handler): void
    {
        $handler->handle($this->articleIds);
    }
}
