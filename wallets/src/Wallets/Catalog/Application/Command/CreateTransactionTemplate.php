<?php

namespace Wallets\Catalog\Application\Command;

use Wallets\Shared\Application\Command;

final class CreateTransactionTemplate implements Command
{
    public function __construct(public readonly int $userId, public readonly array $data) {}
}
