<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

class EnrichArticleHandler
{
    public function __construct(
        private readonly BatchEnrichArticlesHandler $batchHandler,
    ) {}

    public function handle(string $articleId): void
    {
        $this->batchHandler->handle([$articleId]);
    }
}
