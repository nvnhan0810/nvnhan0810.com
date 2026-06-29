<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigestRunItemModel extends Model
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
        return $this->belongsTo(DigestRunModel::class, 'digest_run_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SubjectModel::class, 'subject_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(DigestArticleModel::class, 'article_id');
    }
}
