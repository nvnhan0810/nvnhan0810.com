<?php

namespace Wallets\Lending\Application\Command;

use Wallets\Shared\Application\Command;

final class SettleLoan implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly int $loanId,
        public readonly array $data,
        public readonly ?float $remainingPrincipal = null,
    ) {}
}
