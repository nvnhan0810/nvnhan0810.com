<?php

namespace Wallets\RecurringPlanning\Application\Handler;

use App\Models\RecurringItem;
use App\Models\Setting;
use Carbon\Carbon;
use Wallets\RecurringPlanning\Application\Command\CreateRecurringItem;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceGenerator;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class CreateRecurringItemHandler implements CommandHandler
{
    public function __construct(private readonly RecurringOccurrenceGenerator $generator) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateRecurringItem);

        $data = $command->data;
        $data['wallet_id'] = null;

        $item = RecurringItem::create(['user_id' => $command->userId, ...$data]);

        $horizon = Carbon::today()->addDays(Setting::recurringAlertDays($command->userId));
        $this->generator->ensure($item, $horizon);

        return $item;
    }
}
