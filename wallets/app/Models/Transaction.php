<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToUser;

    public const TYPES = [
        'income' => 'Thu',
        'expense' => 'Chi',
        'adjustment' => 'Cân đối',
    ];

    public const ADJUSTMENT_DIRECTIONS = [
        'increase' => 'Tăng số dư',
        'decrease' => 'Giảm số dư',
    ];

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'adjustment_direction',
        'amount',
        'description',
        'category',
        'transaction_template_id',
        'transacted_at',
        'note',
        'loan_id',
        'loan_payment_id',
        'wallet_transfer_id',
        'recurring_item_id',
        'recurring_occurrence_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transacted_at' => 'date',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class, 'transaction_template_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'loan_payment_id');
    }

    public function walletTransfer(): BelongsTo
    {
        return $this->belongsTo(WalletTransfer::class);
    }

    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    public function recurringOccurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringOccurrence::class);
    }

    public function isFromLoan(): bool
    {
        return $this->loan_id !== null || $this->loan_payment_id !== null;
    }

    public function isTransfer(): bool
    {
        return $this->wallet_transfer_id !== null;
    }

    public function isAdjustment(): bool
    {
        return $this->type === 'adjustment';
    }

    public function typeLabel(): string
    {
        if ($this->isTransfer()) {
            return 'Chuyển ví';
        }

        if ($this->isAdjustment()) {
            $dir = self::ADJUSTMENT_DIRECTIONS[$this->adjustment_direction] ?? '';

            return 'Cân đối'.($dir ? " ({$dir})" : '');
        }

        return self::TYPES[$this->type] ?? $this->type;
    }

    public function signedAmountForDisplay(): string
    {
        if ($this->isTransfer()) {
            return ($this->type === 'income' ? '+' : '-').number_format($this->amount, 0);
        }

        if ($this->type === 'adjustment') {
            $sign = $this->adjustment_direction === 'increase' ? '+' : '-';

            return $sign.number_format($this->amount, 0);
        }

        return ($this->type === 'income' ? '+' : '-').number_format($this->amount, 0);
    }

    public function displayColorClass(): string
    {
        if ($this->type === 'income' || ($this->isAdjustment() && $this->adjustment_direction === 'increase')) {
            return 'text-green-600';
        }

        return 'text-red-600';
    }
}
