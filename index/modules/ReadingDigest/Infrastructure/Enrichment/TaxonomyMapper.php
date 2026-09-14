<?php

namespace Modules\ReadingDigest\Infrastructure\Enrichment;

use App\Models\RdArticle;
use App\Models\RdSourceTagMapping;
use App\Models\RdTaxonomyNode;

class TaxonomyMapper
{
    public function mapRawTags(string $sourceId, array $rawTags): array
    {
        $mappings = RdSourceTagMapping::query()
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

        $nodes = RdTaxonomyNode::query()->whereIn('path', $paths)->get();

        return $nodes->map(fn ($node) => [
            'taxonomy_node_id' => $node->id,
            'path' => $node->path,
            'confidence' => 0.75,
        ])->all();
    }

    public function syncArticleTaxonomy(RdArticle $article, array $taxonomyEntries): void
    {
        $sync = [];
        foreach ($taxonomyEntries as $entry) {
            $sync[$entry['taxonomy_node_id']] = ['confidence' => $entry['confidence'] ?? 1.0];
        }
        $article->taxonomyNodes()->sync($sync);
    }
}
