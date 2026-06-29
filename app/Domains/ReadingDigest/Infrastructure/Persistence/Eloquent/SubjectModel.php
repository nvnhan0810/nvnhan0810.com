<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubjectModel extends Model
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
            SourceModel::class,
            'rd_subject_source',
            'subject_id',
            'source_id'
        )->withPivot('config');
    }
}
