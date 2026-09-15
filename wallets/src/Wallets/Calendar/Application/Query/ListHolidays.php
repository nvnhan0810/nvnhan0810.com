<?php

namespace Wallets\Calendar\Application\Query;

use Wallets\Shared\Application\Query;

final class ListHolidays implements Query
{
    public function __construct(public readonly int $perPage = 50) {}
}
