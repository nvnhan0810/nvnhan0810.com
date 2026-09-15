<?php

namespace Wallets\Catalog\Application\Handler;

use App\Models\TransactionTemplate;
use Wallets\Catalog\Application\Command\DeleteTransactionTemplate;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class DeleteTransactionTemplateHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteTransactionTemplate);
        TransactionTemplate::query()->forUser($command->userId)->findOrFail($command->templateId)->delete();

        return null;
    }
}
