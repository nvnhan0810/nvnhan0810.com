<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Query;

use Modules\Shared\Application\Query;

final class GetSyncChanges implements Query
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $since,
    ) {}
}
