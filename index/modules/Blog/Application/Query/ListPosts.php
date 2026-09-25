<?php

namespace Modules\Blog\Application\Query;

use Modules\Shared\Application\Query;

final class ListPosts implements Query
{
    public function __construct(
        public readonly bool $authenticated,
        public readonly string $search = '',
        public readonly string $tag = '',
        public readonly string $statusFilter = '',
        public readonly ?int $editId = null,
    ) {}
}
