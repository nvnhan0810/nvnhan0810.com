<?php

namespace Modules\Sso\Application\DTOs;

final class SsoUserClaims
{
    public function __construct(
        public readonly int $sub,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $avatar,
    ) {}

    /** @return array{sub: int, email: string, name: string, avatar: string|null} */
    public function toArray(): array
    {
        return [
            'sub' => $this->sub,
            'email' => $this->email,
            'name' => $this->name,
            'avatar' => $this->avatar,
        ];
    }
}
