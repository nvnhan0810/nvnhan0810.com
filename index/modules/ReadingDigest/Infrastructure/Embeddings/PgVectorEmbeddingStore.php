<?php

namespace Modules\ReadingDigest\Infrastructure\Embeddings;

use App\Models\RdArticle;
use App\Models\RdArticleEmbedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PgVectorEmbeddingStore
{
    public function __construct(
        private readonly AiEmbeddingClient $client,
    ) {}

    public function embedArticle(RdArticle $article): void
    {
        $this->embedArticles(collect([$article]));
    }

    /**
     * @param  Collection<int, RdArticle>  $articles
     */
    public function embedArticles(Collection $articles): void
    {
        if ($articles->isEmpty()) {
            return;
        }

        $texts = $articles->map(fn (RdArticle $article) => $this->embeddingText($article))->all();
        $vectors = $this->client->embedBatch($texts);

        foreach ($articles->values() as $index => $article) {
            $vector = $vectors[$index] ?? null;
            if (! $vector) {
                continue;
            }

            RdArticleEmbedding::updateOrCreate(
                ['article_id' => $article->id],
                ['vector' => $vector, 'model' => 'paraphrase-multilingual-MiniLM-L12-v2']
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

    private function embeddingText(RdArticle $article): string
    {
        return trim(implode("\n\n", array_filter([
            $article->title,
            $article->summary,
            mb_substr($article->content_text ?? '', 0, 2000),
        ])));
    }
}
