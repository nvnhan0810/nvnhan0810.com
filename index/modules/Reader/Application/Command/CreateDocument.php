<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class CreateDocument implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $id,
        public readonly string $title,
        public readonly int $pageCount,
    ) {}
}
