<?php

namespace Wallets\Calendar\Application\Command;

use Wallets\Shared\Application\Command;

final class CreateHoliday implements Command
{
    public function __construct(public readonly array $data) {}
}
