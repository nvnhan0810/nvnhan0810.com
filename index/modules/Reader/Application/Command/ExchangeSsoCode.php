<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class ExchangeSsoCode implements Command
{
    public function __construct(
        public readonly string $code,
        public readonly string $redirectUri,
        public readonly string $deviceName,
        public readonly string $clientId,
    ) {}
}
