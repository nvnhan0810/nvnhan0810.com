<?php

namespace Wallets\WalletAccounting\Application\Query;

use Wallets\Shared\Application\Query;

final class ListWallets implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $activeOnly = false,
        public readonly bool $withTransactionCount = false,
    ) {}
}
