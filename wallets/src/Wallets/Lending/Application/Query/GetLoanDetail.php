<?php

namespace Wallets\Lending\Application\Query;

use Wallets\Shared\Application\Query;

final class GetLoanDetail implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly int $loanId,
    ) {}
}
