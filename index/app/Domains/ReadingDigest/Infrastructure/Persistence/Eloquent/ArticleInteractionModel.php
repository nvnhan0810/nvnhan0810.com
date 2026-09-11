<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleInteractionModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'rd_article_interactions';

    protected $fillable = [
        'user_id',
        'article_id',
        'event',
        'metadata',
        'subject_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(DigestArticleModel::class, 'article_id');
    }
}
