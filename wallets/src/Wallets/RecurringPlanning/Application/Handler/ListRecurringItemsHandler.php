<?php

namespace Wallets\RecurringPlanning\Application\Handler;

use App\Models\RecurringItem;
use Wallets\RecurringPlanning\Application\Query\ListRecurringItems;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class ListRecurringItemsHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof ListRecurringItems);

        return RecurringItem::query()
            ->forUser($query->userId)
            ->orderByDesc('is_active')
            ->orderBy('day_of_month')
            ->orderBy('name')
            ->get()
            ->map(function (RecurringItem $item) {
                $item->next_due = $item->nextDueDate();
                $item->days_until = $item->daysUntilDue();
                $item->insufficient_funds = false;

                return $item;
            });
    }
}
