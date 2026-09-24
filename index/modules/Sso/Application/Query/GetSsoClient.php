<?php

namespace Modules\Sso\Application\Query;

use Modules\Shared\Application\Query;

final class GetSsoClient implements Query
{
    public function __construct(
        public readonly string $id,
    ) {}
}
