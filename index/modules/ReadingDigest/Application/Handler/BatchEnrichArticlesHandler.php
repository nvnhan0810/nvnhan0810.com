<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Jobs\ReadingDigest\BatchEmbedArticlesJob;
use App\Models\RdArticle;
use Modules\ReadingDigest\Domain\Services\ArticleEnrichmentPolicy;
use Modules\ReadingDigest\Infrastructure\Enrichment\ArticleMetadataClient;
use Modules\ReadingDigest\Infrastructure\Enrichment\TaxonomyMapper;

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
            $articles = RdArticle::query()->whereIn('id', $chunkIds)->get();

            if ($articles->isEmpty()) {
                continue;
            }

            $payload = $articles->map(fn (RdArticle $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'content_text' => $article->content_text,
            ])->values()->all();

            $enrichedById = $this->metadataClient->enrichBatch($payload);

            foreach ($articles as $article) {
                $enriched = $enrichedById[$article->id] ?? [];
                $summary = $enriched['summary'] ?? null;
                unset($enriched['summary']);

                $rawTags = $article->metadata['raw_tags'] ?? [];
                $aiTags = $enriched['topics'] ?? [];
                $fromTags = $this->taxonomyMapper->mapRawTags(
                    $article->source_id,
                    array_values(array_unique(array_merge($rawTags, $aiTags))),
                );
                $fromPaths = $this->taxonomyMapper->mapPaths($enriched['taxonomy_paths'] ?? []);

                $merged = collect(array_merge($fromTags, $fromPaths))
                    ->unique('taxonomy_node_id')
                    ->values()
                    ->all();

                $metadata = array_merge($article->metadata ?? [], $enriched, [
                    'taxonomy_ids' => collect($merged)->pluck('path')->all(),
                ]);

                $article->update([
                    'summary' => is_string($summary) && $summary !== '' ? $summary : $article->summary,
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
