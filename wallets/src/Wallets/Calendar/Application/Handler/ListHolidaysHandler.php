<?php

namespace Wallets\Calendar\Application\Handler;

use App\Models\Holiday;
use Wallets\Calendar\Application\Query\ListHolidays;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class ListHolidaysHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof ListHolidays);

        return Holiday::orderBy('date', 'desc')->paginate($query->perPage);
    }
}
