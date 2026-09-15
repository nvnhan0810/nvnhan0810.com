<?php

namespace Wallets\Preferences\Application\Query;

use Wallets\Shared\Application\Query;

final class GetSettings implements Query
{
    public function __construct(public readonly int $userId) {}
}
