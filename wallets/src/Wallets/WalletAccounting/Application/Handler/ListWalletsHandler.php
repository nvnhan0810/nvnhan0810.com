<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Wallet;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;
use Wallets\WalletAccounting\Application\Query\ListWallets;

final class ListWalletsHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof ListWallets);

        $q = Wallet::query()
            ->forUser($query->userId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_active')
            ->orderBy('order')
            ->orderBy('name');

        if ($query->activeOnly) {
            $q->where('is_active', true);
        }

        if ($query->withTransactionCount) {
            $q->withCount('transactions');
        }

        return $q->get();
    }
}
