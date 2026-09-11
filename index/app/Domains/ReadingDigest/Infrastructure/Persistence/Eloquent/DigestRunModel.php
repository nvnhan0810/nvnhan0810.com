<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigestRunModel extends Model
{
    use HasUuids;

    protected $table = 'rd_digest_runs';

    protected $fillable = [
        'user_id',
        'run_date',
        'status',
        'stats',
        'telegram_sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'stats' => 'array',
            'telegram_sent_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DigestRunItemModel::class, 'digest_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
