<?php

namespace Modules\ReadingDigest\Application\Query;

use Modules\Shared\Application\Query;

final class GetTodayDigest implements Query
{
    public function __construct(
        public readonly int $userId,
        public readonly string $date,
    ) {}
}
