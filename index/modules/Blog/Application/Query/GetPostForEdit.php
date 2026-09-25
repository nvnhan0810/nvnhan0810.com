<?php

namespace Modules\Blog\Application\Query;

use Modules\Shared\Application\Query;

final class GetPostForEdit implements Query
{
    public function __construct(
        public readonly int $id,
    ) {}
}
