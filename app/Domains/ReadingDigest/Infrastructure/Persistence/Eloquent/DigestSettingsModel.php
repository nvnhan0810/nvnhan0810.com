<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DigestSettingsModel extends Model
{
    use HasUuids;

    protected $table = 'rd_digest_settings';

    protected $fillable = [
        'user_id',
        'notification_time',
        'timezone',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
