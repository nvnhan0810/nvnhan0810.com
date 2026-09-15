<?php

namespace Wallets\Calendar\Application\Command;

use Wallets\Shared\Application\Command;

final class ImportHolidays implements Command
{
    /**
     * @param  list<array{date: string, name: string, type: string}>  $rows
     */
    public function __construct(public readonly array $rows) {}
}
