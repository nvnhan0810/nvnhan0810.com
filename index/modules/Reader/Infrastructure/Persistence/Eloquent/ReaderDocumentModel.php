<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ReaderDocumentModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'reader_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'page_count',
        'content_type',
        'byte_size',
        'content_sha256',
        'seaweed_pdf_key',
        'seaweed_thumb_key',
        'status',
        'is_favorite',
        'revision',
        'deleted_at',
        'last_opened_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'byte_size' => 'integer',
            'is_favorite' => 'boolean',
            'revision' => 'integer',
            'deleted_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
