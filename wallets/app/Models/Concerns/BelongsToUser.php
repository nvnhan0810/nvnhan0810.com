<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where($this->getTable().'.user_id', $userId);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        $query = static::query()->where($field, $value);

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }

        return $query->firstOrFail();
    }
}
