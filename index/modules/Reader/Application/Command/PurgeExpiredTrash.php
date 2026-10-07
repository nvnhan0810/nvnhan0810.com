<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use DateTimeImmutable;
use Modules\Shared\Application\Command;

final class PurgeExpiredTrash implements Command
{
    public function __construct(
        public readonly ?DateTimeImmutable $now = null,
    ) {}
}
