<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class PurgeDocument implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $documentId,
    ) {}
}
