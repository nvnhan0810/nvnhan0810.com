<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

final readonly class IdpUserClaims
{
    public function __construct(
        public int $sub,
        public string $email,
        public string $name,
        public ?string $avatar,
    ) {}
}
