<?php

namespace Modules\Sso\Domain;

use Modules\Sso\Domain\Exceptions\UnknownSsoClientException;

final class SsoClientRegistry
{
    /** @var array<string, SsoClient> */
    private array $clients;

    public function __construct()
    {
        $sharedSecret = (string) config('sso.secret', '');
        $this->clients = [];

        if ($sharedSecret === '') {
            return;
        }

        foreach ((array) config('sso.clients', []) as $id => $definition) {
            if (! is_string($id) || ! is_array($definition)) {
                continue;
            }

            $this->clients[$id] = new SsoClient(
                $id,
                $sharedSecret,
                array_values(array_filter((array) ($definition['redirect_uris'] ?? []))),
                array_values(array_filter((array) ($definition['redirect_uri_patterns'] ?? []))),
            );
        }
    }

    public function get(string $clientId): SsoClient
    {
        if (! isset($this->clients[$clientId])) {
            throw new UnknownSsoClientException($clientId);
        }

        return $this->clients[$clientId];
    }

    public function has(string $clientId): bool
    {
        return isset($this->clients[$clientId]);
    }
}
