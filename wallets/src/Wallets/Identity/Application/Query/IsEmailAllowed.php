<?php

namespace Wallets\Identity\Application\Query;

use Wallets\Shared\Application\Query;

final class IsEmailAllowed implements Query
{
    public function __construct(public readonly string $email) {}
}
