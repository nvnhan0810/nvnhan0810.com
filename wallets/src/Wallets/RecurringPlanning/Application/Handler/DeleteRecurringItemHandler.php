<?php

namespace Wallets\RecurringPlanning\Application\Handler;

use App\Models\RecurringItem;
use Wallets\RecurringPlanning\Application\Command\DeleteRecurringItem;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class DeleteRecurringItemHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteRecurringItem);
        RecurringItem::query()->forUser($command->userId)->findOrFail($command->recurringItemId)->delete();

        return null;
    }
}
