<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class CreateCollection implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $name,
        public readonly ?string $id = null,
    ) {}
}
