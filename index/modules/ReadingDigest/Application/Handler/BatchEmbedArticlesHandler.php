<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use Modules\ReadingDigest\Domain\Services\ArticleFreshnessPolicy;
use Modules\ReadingDigest\Infrastructure\Embeddings\PgVectorEmbeddingStore;

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

        $articles = RdArticle::query()
            ->whereIn('id', $articleIds)
            ->get()
            ->filter(fn (RdArticle $article) => ArticleFreshnessPolicy::isEligible($article));

        if ($articles->isEmpty()) {
            return;
        }

        $this->embeddingStore->embedArticles($articles);
    }
}
