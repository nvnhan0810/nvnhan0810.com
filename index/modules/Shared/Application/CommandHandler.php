<?php

namespace Modules\Shared\Application;

interface CommandHandler
{
    public function handle(Command $command): mixed;
}
