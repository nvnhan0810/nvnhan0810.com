<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Services\ArticleEnrichmentPolicy;
use App\Domains\ReadingDigest\Infrastructure\Enrichment\ArticleMetadataClient;
use App\Domains\ReadingDigest\Infrastructure\Enrichment\TaxonomyMapper;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Presentation\Jobs\BatchEmbedArticlesJob;

class BatchEnrichArticlesHandler
{
    public function __construct(
        private readonly ArticleMetadataClient $metadataClient,
        private readonly TaxonomyMapper $taxonomyMapper,
    ) {}

    /**
     * @param  array<int, string>  $articleIds
     */
    public function handle(array $articleIds): void
    {
        $eligibleIds = ArticleEnrichmentPolicy::filterEligibleIds($articleIds);

        if ($eligibleIds === []) {
            return;
        }

        $batchSize = (int) config('reading-digest.enrich_batch_size', 10);
        $embeddedIds = [];

        foreach (array_chunk($eligibleIds, $batchSize) as $chunkIds) {
            $articles = DigestArticleModel::query()->whereIn('id', $chunkIds)->get();

            if ($articles->isEmpty()) {
                continue;
            }

            $payload = $articles->map(fn (DigestArticleModel $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'content_text' => $article->content_text,
            ])->values()->all();

            $enrichedById = $this->metadataClient->enrichBatch($payload);

            foreach ($articles as $article) {
                $enriched = $enrichedById[$article->id] ?? [];

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

                $embeddedIds[] = $article->id;
            }
        }

        if ($embeddedIds !== []) {
            BatchEmbedArticlesJob::dispatch($embeddedIds);
        }
    }
}
