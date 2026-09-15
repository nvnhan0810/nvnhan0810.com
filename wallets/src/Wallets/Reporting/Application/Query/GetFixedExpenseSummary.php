<?php

namespace Wallets\Reporting\Application\Query;

use Wallets\Shared\Application\Query;

final class GetFixedExpenseSummary implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly string $granularity = 'month',
        public readonly ?string $start = null,
        public readonly int $duration = 5,
        public readonly int $customDays = 30,
    ) {}
}
