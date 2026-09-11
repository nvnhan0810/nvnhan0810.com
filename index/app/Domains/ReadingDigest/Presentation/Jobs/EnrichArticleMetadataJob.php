<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\EnrichArticleHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichArticleMetadataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $articleId) {}

    public function handle(EnrichArticleHandler $handler): void
    {
        $handler->handle($this->articleId);
    }
}
