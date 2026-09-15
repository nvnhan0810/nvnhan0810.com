<?php

namespace Wallets\RecurringPlanning\Application\Query;

use Wallets\Shared\Application\Query;

final class ListRecurringItems implements Query
{
    public function __construct(
        public readonly int $userId,
    ) {}
}
