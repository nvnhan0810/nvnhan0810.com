<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoPomodoroState extends Model
{
    protected $fillable = [
        'user_id',
        'focus_minutes',
        'short_break_minutes',
        'sessions_before_long_break',
        'long_break_minutes',
        'phase',
        'remaining_ms',
        'ends_at',
        'focus_count',
        'active_todo_id',
        'is_running',
        'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'focus_minutes' => 'integer',
            'short_break_minutes' => 'integer',
            'sessions_before_long_break' => 'integer',
            'long_break_minutes' => 'integer',
            'remaining_ms' => 'integer',
            'ends_at' => 'integer',
            'focus_count' => 'integer',
            'active_todo_id' => 'integer',
            'is_running' => 'boolean',
            'client_updated_at' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeTodo(): BelongsTo
    {
        return $this->belongsTo(Todo::class, 'active_todo_id');
    }
}
