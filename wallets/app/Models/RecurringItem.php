<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringItem extends Model
{
    use BelongsToUser;

    public const TYPES = [
        'income' => 'Thu cố định',
        'expense' => 'Chi cố định',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'amount',
        'wallet_id',
        'day_of_month',
        'effective_from',
        'ends_at',
        'is_active',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'ends_at' => 'date',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function nextDueDate(?Carbon $from = null): Carbon
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $day = min($this->day_of_month, $from->daysInMonth);
        $due = $from->copy()->day($day);

        if ($due->lt($from)) {
            $next = $from->copy()->addMonthNoOverflow()->startOfMonth();
            $day = min($this->day_of_month, $next->daysInMonth);

            return $next->day($day);
        }

        return $due;
    }

    public function daysUntilDue(?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();

        return (int) $from->diffInDays($this->nextDueDate($from), false);
    }

    public function isInsufficientFunds(): bool
    {
        return false;
    }
}
