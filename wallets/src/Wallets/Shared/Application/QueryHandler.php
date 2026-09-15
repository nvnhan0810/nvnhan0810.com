<?php

namespace Wallets\Shared\Application;

interface QueryHandler
{
    public function handle(Query $query): mixed;
}
