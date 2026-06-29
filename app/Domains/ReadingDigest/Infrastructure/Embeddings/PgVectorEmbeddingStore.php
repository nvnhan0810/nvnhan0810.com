<?php

namespace App\Domains\ReadingDigest\Infrastructure\Embeddings;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\ArticleEmbeddingModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use Illuminate\Support\Facades\DB;

class PgVectorEmbeddingStore
{
    public function __construct(
        private readonly OpenAiEmbeddingClient $client,
    ) {}

    public function embedArticle(DigestArticleModel $article): void
    {
        $text = trim(implode("\n\n", array_filter([
            $article->title,
            $article->summary,
            mb_substr($article->content_text ?? '', 0, 2000),
        ])));

        $vector = $this->client->embed($text);
        if (! $vector) {
            return;
        }

        $model = config('reading-digest.embedding_model', 'text-embedding-3-small');

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
