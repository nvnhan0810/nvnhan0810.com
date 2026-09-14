<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RdDigestSettings extends Model
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
