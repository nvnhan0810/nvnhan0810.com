<?php

declare(strict_types=1);

namespace Modules\Reader\Application\DTOs;

use Modules\Reader\Domain\Entities\ReaderUser;

final readonly class AuthTokenResult
{
    public function __construct(
        public string $token,
        public ReaderUser $user,
    ) {}
}
