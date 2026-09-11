<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInterestScoreModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'rd_user_interest_scores';

    protected $fillable = [
        'user_id',
        'taxonomy_node_id',
        'score',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'updated_at' => 'datetime',
        ];
    }

    public function taxonomyNode(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNodeModel::class, 'taxonomy_node_id');
    }
}
