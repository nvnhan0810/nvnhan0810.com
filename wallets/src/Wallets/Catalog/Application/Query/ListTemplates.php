<?php

namespace Wallets\Catalog\Application\Query;

use Wallets\Shared\Application\Query;

final class ListTemplates implements Query
{
    public function __construct(public readonly int $userId) {}
}
