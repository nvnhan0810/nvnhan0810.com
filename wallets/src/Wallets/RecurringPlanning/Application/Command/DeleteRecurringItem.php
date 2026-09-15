<?php

namespace Wallets\RecurringPlanning\Application\Command;

use Wallets\Shared\Application\Command;

final class DeleteRecurringItem implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly int $recurringItemId,
    ) {}
}
