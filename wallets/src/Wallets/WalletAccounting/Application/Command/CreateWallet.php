<?php

namespace Wallets\WalletAccounting\Application\Command;

use Wallets\Shared\Application\Command;

final class CreateWallet implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
    ) {}
}
