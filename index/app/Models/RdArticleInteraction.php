<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RdArticleInteraction extends Model
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
        return $this->belongsTo(RdArticle::class, 'article_id');
    }
}
