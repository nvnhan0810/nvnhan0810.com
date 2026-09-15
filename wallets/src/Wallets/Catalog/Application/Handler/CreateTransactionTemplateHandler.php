<?php

namespace Wallets\Catalog\Application\Handler;

use App\Models\TransactionTemplate;
use Wallets\Catalog\Application\Command\CreateTransactionTemplate;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class CreateTransactionTemplateHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateTransactionTemplate);

        return TransactionTemplate::create(['user_id' => $command->userId, ...$command->data]);
    }
}
