<?php

namespace Modules\Shared\Application;

interface QueryBus
{
    public function ask(Query $query): mixed;
}
