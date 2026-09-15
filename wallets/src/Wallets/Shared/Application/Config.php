<?php

namespace Wallets\Shared\Application;

interface Config
{
    public function get(string $key, mixed $default = null): mixed;
}
