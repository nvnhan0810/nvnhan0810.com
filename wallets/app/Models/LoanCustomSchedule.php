<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCustomSchedule extends Model
{
    use BelongsToUser;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DUE = 'due';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_PAID = 'paid';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'loan_id',
        'user_id',
        'month_index',
        'due_date',
        'payment',
        'principal',
        'interest',
        'fee',
        'remaining_principal',
        'status',
        'payment_id',
        'paid_amount',
        'paid_at',
        'reminded_telegram_at',
        'note',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'date',
        'reminded_telegram_at' => 'datetime',
        'payment' => 'decimal:2',
        'principal' => 'decimal:2',
        'interest' => 'decimal:2',
        'fee' => 'decimal:2',
        'remaining_principal' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_DUE, self::STATUS_OVERDUE], true);
    }

    public function markPaid(Payment $payment): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'payment_id' => $payment->id,
            'paid_amount' => $payment->amount,
            'paid_at' => $payment->paid_at,
        ]);
    }

    public function daysUntilDue(?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();

        return (int) $from->diffInDays($this->due_date, false);
    }
}
