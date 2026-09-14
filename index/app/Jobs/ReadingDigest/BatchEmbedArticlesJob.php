<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\BatchEmbedArticlesHandler;

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
