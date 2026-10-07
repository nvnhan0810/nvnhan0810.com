<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use Modules\Reader\Domain\Entities\IdpUserClaims;

interface IdpTokenClient
{
    public function exchangeAuthorizationCode(
        string $code,
        string $redirectUri,
        string $clientId,
    ): IdpUserClaims;
}
