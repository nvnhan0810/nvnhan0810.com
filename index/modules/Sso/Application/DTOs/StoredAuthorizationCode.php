<?php

namespace Modules\Sso\Application\DTOs;

final class StoredAuthorizationCode
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly int $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $avatar,
    ) {}
}
