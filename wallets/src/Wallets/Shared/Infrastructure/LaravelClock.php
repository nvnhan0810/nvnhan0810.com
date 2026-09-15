<?php

namespace Wallets\Shared\Infrastructure;

use DateTimeImmutable;
use Wallets\Shared\Application\Clock;

final class LaravelClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface(now());
    }
}
