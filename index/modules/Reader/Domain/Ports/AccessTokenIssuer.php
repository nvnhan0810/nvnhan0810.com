<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use Modules\Reader\Domain\Entities\ReaderUser;

interface AccessTokenIssuer
{
    public function issue(ReaderUser $user, string $deviceName): string;

    public function revokeCurrentToken(string $userId, string $tokenId): void;
}
