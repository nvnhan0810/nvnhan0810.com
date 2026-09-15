<?php

namespace Modules\Sso\Domain\Ports;

use Modules\Sso\Application\DTOs\StoredAuthorizationCode;

interface AuthorizationCodeStore
{
    public function put(string $code, StoredAuthorizationCode $payload, int $ttlSeconds): void;

    public function pull(string $code): ?StoredAuthorizationCode;
}
