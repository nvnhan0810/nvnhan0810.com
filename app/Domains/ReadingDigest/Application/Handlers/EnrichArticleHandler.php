<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Enrichment\OpenAiMetadataClient;
use App\Domains\ReadingDigest\Infrastructure\Enrichment\TaxonomyMapper;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Presentation\Jobs\EmbedArticleJob;

class EnrichArticleHandler
{
    public function __construct(
        private readonly OpenAiMetadataClient $metadataClient,
        private readonly TaxonomyMapper $taxonomyMapper,
    ) {}

    public function handle(string $articleId): void
    {
        $article = DigestArticleModel::query()->findOrFail($articleId);

        $enriched = $this->metadataClient->enrich(
            $article->title,
            $article->summary,
            $article->content_text
        );

        $rawTags = $article->metadata['raw_tags'] ?? [];
        $fromTags = $this->taxonomyMapper->mapRawTags($article->source_id, $rawTags);
        $fromPaths = $this->taxonomyMapper->mapPaths($enriched['taxonomy_paths'] ?? []);

        $merged = collect(array_merge($fromTags, $fromPaths))
            ->unique('taxonomy_node_id')
            ->values()
            ->all();

        $metadata = array_merge($article->metadata ?? [], $enriched, [
            'taxonomy_ids' => collect($merged)->pluck('path')->all(),
        ]);

        $article->update([
            'metadata' => $metadata,
            'enriched_at' => now(),
        ]);

        if ($merged !== []) {
            $this->taxonomyMapper->syncArticleTaxonomy($article, $merged);
        }

        EmbedArticleJob::dispatch($article->id);
    }
}
