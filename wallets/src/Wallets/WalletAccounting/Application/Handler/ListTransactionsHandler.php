<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Transaction;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;
use Wallets\WalletAccounting\Application\Query\ListTransactions;

final class ListTransactionsHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof ListTransactions);

        $q = Transaction::query()
            ->forUser($query->userId)
            ->with(['wallet', 'walletTransfer.fromWallet', 'walletTransfer.toWallet'])
            ->orderByDesc('transacted_at')
            ->orderByDesc('id');

        if ($query->walletId) {
            $q->where('wallet_id', $query->walletId);
        }

        if ($query->type && in_array($query->type, ['income', 'expense', 'adjustment', 'transfer'], true)) {
            if ($query->type === 'transfer') {
                $q->whereNotNull('wallet_transfer_id');
            } else {
                $q->where('type', $query->type)->whereNull('wallet_transfer_id');
            }
        }

        return $q->paginate($query->perPage)->withQueryString();
    }
}
