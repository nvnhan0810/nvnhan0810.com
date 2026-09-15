<?php

namespace Wallets\RecurringPlanning\Application\Command;

use Wallets\Shared\Application\Command;

final class CreateRecurringItem implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
    ) {}
}
