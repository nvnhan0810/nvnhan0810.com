<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const KIND_EARLY = 'early';

    public const KIND_PERIOD = 'period';

    public const KIND_SETTLEMENT = 'settlement';

    protected $fillable = [
        'loan_id',
        'kind',
        'amount',
        'paid_at',
        'period_due_date',
        'schedule_month_index',
        'reduces_principal',
        'note',
        'transaction_id',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'period_due_date' => 'date',
        'amount' => 'decimal:2',
        'reduces_principal' => 'boolean',
    ];

    public function isEarly(): bool
    {
        return $this->kind === self::KIND_EARLY;
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_EARLY => 'Thanh toán trước',
            self::KIND_SETTLEMENT => 'Tất toán',
            default => 'Thanh toán kỳ',
        };
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

