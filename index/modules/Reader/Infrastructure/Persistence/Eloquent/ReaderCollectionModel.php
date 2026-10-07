<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ReaderCollectionModel extends Model
{
    use HasUuids;

    protected $table = 'reader_collections';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'revision',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<ReaderDocumentModel, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(
            ReaderDocumentModel::class,
            'reader_collection_documents',
            'collection_id',
            'document_id',
        )->withPivot('added_at');
    }
}
