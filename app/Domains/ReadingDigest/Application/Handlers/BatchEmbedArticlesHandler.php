<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Embeddings\PgVectorEmbeddingStore;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;

class BatchEmbedArticlesHandler
{
    public function __construct(
        private readonly PgVectorEmbeddingStore $embeddingStore,
    ) {}

    /**
     * @param  array<int, string>  $articleIds
     */
    public function handle(array $articleIds): void
    {
        if ($articleIds === []) {
            return;
        }

        $articles = DigestArticleModel::query()->whereIn('id', $articleIds)->get();

        if ($articles->isEmpty()) {
            return;
        }

        $this->embeddingStore->embedArticles($articles);
    }
}
