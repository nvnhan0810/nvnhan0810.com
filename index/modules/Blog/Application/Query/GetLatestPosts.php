<?php

namespace Modules\Blog\Application\Query;

use Modules\Shared\Application\Query;

final class GetLatestPosts implements Query
{
    public function __construct(
        public readonly int $limit = 10,
    ) {}
}
