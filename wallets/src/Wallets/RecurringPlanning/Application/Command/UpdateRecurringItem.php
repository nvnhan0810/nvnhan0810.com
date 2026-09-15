<?php

namespace Wallets\RecurringPlanning\Application\Command;

use Wallets\Shared\Application\Command;

final class UpdateRecurringItem implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly int $recurringItemId,
        public readonly array $data,
    ) {}
}
