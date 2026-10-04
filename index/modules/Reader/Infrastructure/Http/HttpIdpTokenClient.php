<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Http;

use Illuminate\Support\Facades\Http;
use Modules\Reader\Domain\Entities\IdpUserClaims;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\IdpTokenClient;

final class HttpIdpTokenClient implements IdpTokenClient
{
    public function exchangeAuthorizationCode(
        string $code,
        string $redirectUri,
        string $clientId,
    ): IdpUserClaims {
        $idpUrl = rtrim((string) config('reader.sso_idp_url'), '/');
        $secret = (string) config('reader.sso_secret');

        if ($idpUrl === '' || $secret === '') {
            throw ReaderDomainException::validation('Reader SSO is not configured');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post($idpUrl.'/api/auth/sso/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $secret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->successful()) {
            throw ReaderDomainException::invalidGrant('IdP rejected authorization code');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if (! isset($data['sub'], $data['email'], $data['name'])) {
            throw ReaderDomainException::invalidGrant('IdP returned incomplete claims');
        }

        return new IdpUserClaims(
            sub: (int) $data['sub'],
            email: (string) $data['email'],
            name: (string) $data['name'],
            avatar: isset($data['avatar']) && is_string($data['avatar']) ? $data['avatar'] : null,
        );
    }
}
