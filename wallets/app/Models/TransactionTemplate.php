<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionTemplate extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
                'name',
        'type',
        'amount',
        'fee',
        'category',
        'description',
        'default_wallet_id',
        'from_wallet_id',
        'to_wallet_id',
        'adjustment_direction',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
    ];

    public function defaultWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'default_wallet_id');
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'transfer' => 'Chuyển ví',
            'adjustment' => 'Cân đối',
            default => Transaction::TYPES[$this->type] ?? $this->type,
        };
    }
}
