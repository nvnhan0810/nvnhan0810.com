<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletTransfer extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
                'from_wallet_id',
        'to_wallet_id',
        'amount',
        'fee',
        'description',
        'note',
        'transacted_at',
        'transaction_template_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'transacted_at' => 'date',
    ];

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class, 'transaction_template_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function totalFromSource(): float
    {
        return (float) $this->amount + (float) $this->fee;
    }
}
