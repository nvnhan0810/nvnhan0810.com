<?php

namespace Wallets\WalletAccounting\Application\Command;

use Wallets\Shared\Application\Command;

final class TransferBetweenWallets implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
        public readonly bool $saveAsTemplate = false,
        public readonly ?string $templateName = null,
    ) {}
}
