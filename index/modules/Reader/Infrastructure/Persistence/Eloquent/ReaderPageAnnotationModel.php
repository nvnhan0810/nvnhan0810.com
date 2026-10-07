<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReaderPageAnnotationModel extends Model
{
    use HasUuids;

    protected $table = 'reader_page_annotations';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'document_id',
        'user_id',
        'page_index',
        'format',
        'seaweed_key',
        'byte_size',
        'content_sha256',
        'revision',
        'updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'page_index' => 'integer',
            'byte_size' => 'integer',
            'revision' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
