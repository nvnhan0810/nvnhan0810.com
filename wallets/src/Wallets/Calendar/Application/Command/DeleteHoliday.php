<?php

namespace Wallets\Calendar\Application\Command;

use Wallets\Shared\Application\Command;

final class DeleteHoliday implements Command
{
    public function __construct(public readonly int $holidayId) {}
}
