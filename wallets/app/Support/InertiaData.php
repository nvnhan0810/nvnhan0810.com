<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Collection;

final class InertiaData
{
    public static function wallet(Wallet $wallet, bool $withCount = false): array
    {
        $data = [
            'id' => $wallet->id,
            'name' => $wallet->name,
            'type' => $wallet->type,
            'type_label' => $wallet->typeLabel(),
            'balance' => (float) $wallet->balance,
            'credit_limit' => $wallet->credit_limit !== null ? (float) $wallet->credit_limit : null,
            'outstanding_balance' => $wallet->outstanding_balance !== null ? (float) $wallet->outstanding_balance : null,
            'statement_day' => $wallet->statement_day,
            'payment_day' => $wallet->payment_day,
            'notes' => $wallet->notes,
            'is_active' => (bool) $wallet->is_active,
            'is_pinned' => (bool) $wallet->is_pinned,
            'order' => (int) $wallet->order,
            'is_credit_card' => $wallet->isCreditCard(),
            'spendable_balance' => $wallet->spendableBalance(),
        ];

        if ($withCount) {
            $data['transactions_count'] = (int) ($wallet->transactions_count ?? 0);
        }

        return $data;
    }

    public static function wallets(Collection $wallets, bool $withCount = false): array
    {
        return $wallets->map(fn (Wallet $w) => self::wallet($w, $withCount))->values()->all();
    }

    public static function transaction(Transaction $tx): array
    {
        return [
            'id' => $tx->id,
            'wallet_id' => $tx->wallet_id,
            'type' => $tx->type,
            'type_label' => $tx->typeLabel(),
            'adjustment_direction' => $tx->adjustment_direction,
            'amount' => (float) $tx->amount,
            'signed_amount' => $tx->signedAmountForDisplay(),
            'display_color_class' => $tx->displayColorClass(),
            'description' => $tx->description,
            'category' => $tx->category,
            'note' => $tx->note,
            'transacted_at' => optional($tx->transacted_at)?->toDateString(),
            'transacted_at_label' => optional($tx->transacted_at)?->format('d/m/Y'),
            'is_transfer' => $tx->isTransfer(),
            'is_adjustment' => $tx->isAdjustment(),
            'is_from_loan' => $tx->isFromLoan(),
            'wallet' => $tx->relationLoaded('wallet') && $tx->wallet ? [
                'id' => $tx->wallet->id,
                'name' => $tx->wallet->name,
            ] : null,
            'wallet_transfer' => $tx->relationLoaded('walletTransfer') && $tx->walletTransfer ? [
                'from_wallet_name' => $tx->walletTransfer->fromWallet?->name,
                'to_wallet_name' => $tx->walletTransfer->toWallet?->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $reminder
     * @return array<string, mixed>
     */
    public static function reminder(array $reminder): array
    {
        $wallet = $reminder['wallet'] ?? null;

        return [
            'kind' => $reminder['kind'] ?? null,
            'name' => $reminder['name'] ?? '',
            'amount' => (float) ($reminder['amount'] ?? 0),
            'due_date' => isset($reminder['due_date']) ? (string) $reminder['due_date'] : null,
            'days_until' => $reminder['days_until'] ?? null,
            'type_label' => $reminder['type_label'] ?? '',
            'type_badge_class' => $reminder['type_badge_class'] ?? '',
            'insufficient_funds' => (bool) ($reminder['insufficient_funds'] ?? false),
            'pay_url' => $reminder['pay_url'] ?? null,
            'wallet_name' => is_object($wallet) ? ($wallet->name ?? null) : null,
        ];
    }

    public static function paginator($paginator): array
    {
        return [
            'data' => $paginator->getCollection()->values()->all(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
