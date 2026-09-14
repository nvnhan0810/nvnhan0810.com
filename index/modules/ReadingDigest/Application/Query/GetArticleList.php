<?php

namespace Modules\ReadingDigest\Application\Query;

use Modules\Shared\Application\Query;

final class GetArticleList implements Query 
{
    public function __construct(public readonly int $perPage = 50) {}
}