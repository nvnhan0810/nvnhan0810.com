<?php

namespace Wallets\Catalog\Application\Command;

use Wallets\Shared\Application\Command;

final class DeleteTransactionTemplate implements Command
{
    public function __construct(public readonly int $userId, public readonly int $templateId) {}
}
