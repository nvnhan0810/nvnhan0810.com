<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReadingProfileModel extends Model
{
    use HasUuids;

    protected $table = 'rd_user_reading_profiles';

    protected $fillable = [
        'user_id',
        'preferences',
        'user_embedding',
        'embedding_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'user_embedding' => 'array',
            'embedding_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
