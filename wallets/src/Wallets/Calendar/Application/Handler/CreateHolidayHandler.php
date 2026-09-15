<?php

namespace Wallets\Calendar\Application\Handler;

use App\Models\Holiday;
use Wallets\Calendar\Application\Command\CreateHoliday;
use Wallets\Lending\Application\LoanScheduleRecalculator;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class CreateHolidayHandler implements CommandHandler
{
    public function __construct(private readonly LoanScheduleRecalculator $loanScheduleRecalculator) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateHoliday);

        $holiday = Holiday::create($command->data);

        $this->loanScheduleRecalculator->recalculateUnsettledDailyLoans();

        return $holiday;
    }
}
