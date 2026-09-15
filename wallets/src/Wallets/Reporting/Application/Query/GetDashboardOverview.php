<?php

namespace Wallets\Reporting\Application\Query;

use Wallets\Shared\Application\Query;

final class GetDashboardOverview implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly string $chartPeriod = 'month',
    ) {}
}
