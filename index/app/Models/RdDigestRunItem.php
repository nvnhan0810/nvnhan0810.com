<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RdDigestRunItem extends Model
{
    use HasUuids;

    protected $table = 'rd_digest_run_items';

    protected $fillable = [
        'digest_run_id',
        'subject_id',
        'article_id',
        'rank',
        'retrieval_score',
        'llm_score',
        'llm_reason',
        'tracking_token',
    ];

    public function digestRun(): BelongsTo
    {
        return $this->belongsTo(RdDigestRun::class, 'digest_run_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(RdSubject::class, 'subject_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(RdArticle::class, 'article_id');
    }
}
