<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Embeddings\PgVectorEmbeddingStore;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;

class EmbedArticleHandler
{
    public function __construct(
        private readonly PgVectorEmbeddingStore $embeddingStore,
    ) {}

    public function handle(string $articleId): void
    {
        $article = DigestArticleModel::query()->findOrFail($articleId);
        $this->embeddingStore->embedArticle($article);
    }
}
