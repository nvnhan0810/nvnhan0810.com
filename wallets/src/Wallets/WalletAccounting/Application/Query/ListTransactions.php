<?php

namespace Wallets\WalletAccounting\Application\Query;

use Wallets\Shared\Application\Query;

final class ListTransactions implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $walletId = null,
        public readonly ?string $type = null,
        public readonly int $perPage = 20,
    ) {}
}
