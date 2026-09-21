<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Todo extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['backlog', 'todo', 'in_progress', 'done', 'rejected'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'started_at',
        'due_at',
        'closed_at',
        'is_urgent',
        'is_important',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_urgent' => 'boolean',
            'is_important' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TodoProject::class, 'project_id');
    }
}
