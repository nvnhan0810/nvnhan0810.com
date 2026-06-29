<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DigestArticleModel extends Model
{
    use HasUuids;

    protected $table = 'rd_articles';

    protected $fillable = [
        'source_id',
        'external_id',
        'url_hash',
        'title',
        'url',
        'summary',
        'content_text',
        'content_html',
        'language',
        'estimated_read_time_minutes',
        'metadata',
        'published_at',
        'fetched_at',
        'force_include',
        'force_exclude',
        'enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'enriched_at' => 'datetime',
            'force_include' => 'boolean',
            'force_exclude' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'source_id');
    }

    public function taxonomyNodes(): BelongsToMany
    {
        return $this->belongsToMany(
            TaxonomyNodeModel::class,
            'rd_article_taxonomy',
            'article_id',
            'taxonomy_node_id'
        )->withPivot('confidence');
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(ArticleEmbeddingModel::class, 'article_id');
    }
}
