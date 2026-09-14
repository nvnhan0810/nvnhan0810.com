<?php

namespace Modules\Shared\Application;

interface CommandBus
{
    public function dispatch(Command $command): mixed;
}
