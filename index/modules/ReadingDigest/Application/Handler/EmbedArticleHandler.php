<?php

namespace Modules\ReadingDigest\Application\Handler;

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
