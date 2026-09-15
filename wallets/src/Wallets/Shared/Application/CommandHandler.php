<?php

namespace Wallets\Shared\Application;

interface CommandHandler
{
    public function handle(Command $command): mixed;
}
