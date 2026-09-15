<?php

namespace Wallets\Lending\Application\Command;

use Wallets\Shared\Application\Command;

final class CreateLoan implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
        public readonly bool $recordCashFlow = false,
        public readonly array $customSchedule = [],
        public readonly ?float $receivedAmount = null,
    ) {}
}
