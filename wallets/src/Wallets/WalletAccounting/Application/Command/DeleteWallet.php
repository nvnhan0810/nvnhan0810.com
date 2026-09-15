<?php

namespace Wallets\WalletAccounting\Application\Command;

use Wallets\Shared\Application\Command;

final class DeleteWallet implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly int $walletId,
    ) {}
}
