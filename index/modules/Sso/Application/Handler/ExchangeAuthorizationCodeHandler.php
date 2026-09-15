<?php

namespace Modules\Sso\Application\Handler;

use Modules\Sso\Application\DTOs\SsoUserClaims;
use Modules\Sso\Domain\Exceptions\UnknownSsoClientException;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;
use Modules\Sso\Domain\SsoClientRegistry;

final class ExchangeAuthorizationCodeHandler
{
    public function __construct(
        private readonly SsoClientRegistry $clients,
        private readonly AuthorizationCodeStore $codes,
    ) {}

    public function handle(
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
    ): SsoUserClaims {
        try {
            $client = $this->clients->get($clientId);
        } catch (UnknownSsoClientException) {
            abort(401, 'invalid_client');
        }

        if (! $client->verifySecret($clientSecret)) {
            abort(401, 'invalid_client');
        }

        $stored = $this->codes->pull($code);

        if ($stored === null) {
            abort(401, 'invalid_grant');
        }

        if ($stored->clientId !== $clientId || $stored->redirectUri !== $redirectUri) {
            abort(401, 'invalid_grant');
        }

        return new SsoUserClaims(
            $stored->userId,
            $stored->email,
            $stored->name,
            $stored->avatar,
        );
    }
}
