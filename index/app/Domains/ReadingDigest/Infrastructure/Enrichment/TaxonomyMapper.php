<?php

namespace App\Domains\ReadingDigest\Infrastructure\Enrichment;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceTagMappingModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\TaxonomyNodeModel;

class TaxonomyMapper
{
    public function mapRawTags(string $sourceId, array $rawTags): array
    {
        $mappings = SourceTagMappingModel::query()
            ->where('source_id', $sourceId)
            ->whereIn('raw_tag', $rawTags)
            ->with('taxonomyNode')
            ->get();

        return $mappings->map(fn ($m) => [
            'taxonomy_node_id' => $m->taxonomy_node_id,
            'path' => $m->taxonomyNode->path,
            'confidence' => 0.9,
        ])->all();
    }

    public function mapPaths(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $nodes = TaxonomyNodeModel::query()->whereIn('path', $paths)->get();

        return $nodes->map(fn ($node) => [
            'taxonomy_node_id' => $node->id,
            'path' => $node->path,
            'confidence' => 0.75,
        ])->all();
    }

    public function syncArticleTaxonomy(DigestArticleModel $article, array $taxonomyEntries): void
    {
        $sync = [];
        foreach ($taxonomyEntries as $entry) {
            $sync[$entry['taxonomy_node_id']] = ['confidence' => $entry['confidence'] ?? 1.0];
        }
        $article->taxonomyNodes()->sync($sync);
    }
}
