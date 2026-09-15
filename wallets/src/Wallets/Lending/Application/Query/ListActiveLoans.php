<?php

namespace Wallets\Lending\Application\Query;

use Wallets\Shared\Application\Query;

final class ListActiveLoans implements Query
{
    public function __construct(public readonly int $userId) {}
}
