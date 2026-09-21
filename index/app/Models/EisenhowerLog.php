<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EisenhowerLog extends Model
{
    protected $fillable = [
        'todo_id',
        'is_urgent',
        'is_important',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'is_important' => 'boolean',
        ];
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
