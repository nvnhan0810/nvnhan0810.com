<?php

namespace Modules\ReadingDigest\Application\Command;

use Modules\Shared\Application\Command;

final class RecordArticleOpened implements Command
{
    public function __construct(
        public readonly string $trackingToken,
        public readonly int $userId,
    ) {}
}
