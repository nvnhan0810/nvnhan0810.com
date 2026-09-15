<?php

namespace Wallets\Catalog\Application\Handler;

use App\Models\TransactionTemplate;
use Wallets\Catalog\Application\Query\ListTemplates;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class ListTemplatesHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof ListTemplates);

        return TransactionTemplate::query()
            ->forUser($query->userId)
            ->with(['defaultWallet', 'fromWallet', 'toWallet'])
            ->orderBy('name')
            ->get();
    }
}
