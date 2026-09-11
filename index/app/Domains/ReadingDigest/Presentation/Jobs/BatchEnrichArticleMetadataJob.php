<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\BatchEnrichArticlesHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BatchEnrichArticleMetadataJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $articleIds
     */
    public function __construct(public readonly array $articleIds) {}

    public function handle(BatchEnrichArticlesHandler $handler): void
    {
        $handler->handle($this->articleIds);
    }
}
