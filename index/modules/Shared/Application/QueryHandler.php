<?php

namespace Modules\Shared\Application;

interface QueryHandler {
    public function handle(Query $query): mixed;
}