<?php

namespace Modules\ReadingDigest\Application\Handler;

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
