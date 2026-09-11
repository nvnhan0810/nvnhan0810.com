<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceTagMappingModel extends Model
{
    use HasUuids;

    protected $table = 'rd_source_tag_mappings';

    protected $fillable = [
        'source_id',
        'raw_tag',
        'taxonomy_node_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'source_id');
    }

    public function taxonomyNode(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNodeModel::class, 'taxonomy_node_id');
    }
}
