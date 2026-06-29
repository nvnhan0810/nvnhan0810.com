<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceModel extends Model
{
    use HasUuids;

    protected $table = 'rd_sources';

    protected $fillable = [
        'name',
        'type',
        'url',
        'fetch_interval_minutes',
        'enabled',
        'config',
        'last_fetch_status',
        'last_fetch_at',
        'last_fetch_error',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
            'last_fetch_at' => 'datetime',
        ];
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            SubjectModel::class,
            'rd_subject_source',
            'source_id',
            'subject_id'
        )->withPivot('config');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(DigestArticleModel::class, 'source_id');
    }

    public function tagMappings(): HasMany
    {
        return $this->hasMany(SourceTagMappingModel::class, 'source_id');
    }
}
