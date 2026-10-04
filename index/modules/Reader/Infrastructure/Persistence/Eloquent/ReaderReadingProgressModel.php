<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ReaderReadingProgressModel extends Model
{
    protected $table = 'reader_reading_progress';

    protected $primaryKey = 'document_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'page_index',
        'revision',
        'updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'page_index' => 'integer',
            'revision' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
