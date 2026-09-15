<?php

namespace Wallets\Lending\Application\Command;

use Wallets\Shared\Application\Command;

final class RecordLoanPayment implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
    ) {}
}
