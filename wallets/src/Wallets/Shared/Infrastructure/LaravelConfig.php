<?php

namespace Wallets\Shared\Infrastructure;

use Wallets\Shared\Application\Config;

final class LaravelConfig implements Config
{
    public function get(string $key, mixed $default = null): mixed
    {
        return config($key, $default);
    }
}
