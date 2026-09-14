<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RdUserReadingProfile extends Model
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
        return $this->belongsTo(User::class);
    }
}
