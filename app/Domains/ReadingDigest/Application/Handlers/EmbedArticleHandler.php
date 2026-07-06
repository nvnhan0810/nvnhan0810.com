<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

class EmbedArticleHandler
{
    public function __construct(
        private readonly BatchEmbedArticlesHandler $batchHandler,
    ) {}

    public function handle(string $articleId): void
    {
        $this->batchHandler->handle([$articleId]);
    }
}
