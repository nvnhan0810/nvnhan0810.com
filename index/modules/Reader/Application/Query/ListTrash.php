<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Query;

use Modules\Shared\Application\Query;

final class ListTrash implements Query
{
    public function __construct(
        public readonly string $userId,
        public readonly int $limit = 50,
        public readonly ?string $cursor = null,
    ) {}
}
