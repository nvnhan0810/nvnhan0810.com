<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrence extends Model
{
    use BelongsToUser;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DUE = 'due';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_POSTED = 'posted';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'recurring_item_id',
        'user_id',
        'due_date',
        'expected_amount',
        'status',
        'transaction_id',
        'posted_amount',
        'posted_at',
        'reminded_telegram_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'posted_at' => 'date',
        'reminded_telegram_at' => 'datetime',
        'expected_amount' => 'decimal:2',
        'posted_amount' => 'decimal:2',
    ];

    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_DUE, self::STATUS_OVERDUE], true);
    }

    public function markPosted(Transaction $transaction): void
    {
        $this->update([
            'status' => self::STATUS_POSTED,
            'transaction_id' => $transaction->id,
            'posted_amount' => $transaction->amount,
            'posted_at' => $transaction->transacted_at,
        ]);
    }

    public function daysUntilDue(?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();

        return (int) $from->diffInDays($this->due_date, false);
    }

    public static function openStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_DUE, self::STATUS_OVERDUE];
    }
}
