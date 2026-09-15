<?php

namespace Wallets\Shared\Infrastructure;

use Illuminate\Support\Facades\Log;
use Wallets\Shared\Application\Logger;

final class LaravelLogger implements Logger
{
    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}
