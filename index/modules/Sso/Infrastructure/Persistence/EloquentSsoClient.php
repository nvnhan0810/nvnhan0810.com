<?php

namespace Modules\Sso\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EloquentSsoClient extends Model
{
    use HasUuids;

    protected $table = 'sso_clients';

    protected $fillable = [
        'client_id',
        'name',
        'domain',
        'redirect_uris',
        'redirect_uri_patterns',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'redirect_uri_patterns' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
