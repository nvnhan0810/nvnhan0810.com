<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleEmbeddingModel extends Model
{
    protected $table = 'rd_article_embeddings';

    protected $primaryKey = 'article_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'article_id',
        'vector',
        'model',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'vector' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(DigestArticleModel::class, 'article_id');
    }
}
