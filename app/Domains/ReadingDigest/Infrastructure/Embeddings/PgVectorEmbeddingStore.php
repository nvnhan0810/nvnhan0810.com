<?php

namespace App\Domains\ReadingDigest\Infrastructure\Embeddings;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\ArticleEmbeddingModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PgVectorEmbeddingStore
{
    public function __construct(
        private readonly GeminiEmbeddingClient $client,
    ) {}

    public function embedArticle(DigestArticleModel $article): void
    {
        $this->embedArticles(collect([$article]));
    }

    /**
     * @param  Collection<int, DigestArticleModel>  $articles
     */
    public function embedArticles(Collection $articles): void
    {
        if ($articles->isEmpty()) {
            return;
        }

        $texts = $articles->map(fn (DigestArticleModel $article) => $this->embeddingText($article))->all();
        $vectors = $this->client->embedBatch($texts);
        $model = config('reading-digest.embedding_model', 'text-embedding-004');

        foreach ($articles->values() as $index => $article) {
            $vector = $vectors[$index] ?? null;
            if (! $vector) {
                continue;
            }

            ArticleEmbeddingModel::updateOrCreate(
                ['article_id' => $article->id],
                ['vector' => $vector, 'model' => $model]
            );

            if (DB::getDriverName() === 'pgsql') {
                try {
                    $literal = '['.implode(',', $vector).']';
                    DB::statement(
                        'UPDATE rd_article_embeddings SET embedding = ?::vector WHERE article_id = ?',
                        [$literal, $article->id]
                    );
                } catch (\Throwable) {
                    // pgvector column optional — JSON vector is sufficient
                }
            }
        }
    }

    private function embeddingText(DigestArticleModel $article): string
    {
        return trim(implode("\n\n", array_filter([
            $article->title,
            $article->summary,
            mb_substr($article->content_text ?? '', 0, 2000),
        ])));
    }
}
