<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RdSubject extends Model
{
    use HasUuids;

    protected $table = 'rd_subjects';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'articles_per_digest',
        'max_age_days',
        'enabled',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'filters' => 'array',
        ];
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            RdSource::class,
            'rd_subject_source',
            'subject_id',
            'source_id'
        )->withPivot('config');
    }
}
