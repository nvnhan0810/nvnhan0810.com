<?php

namespace Modules\Sso\Domain;

use Modules\Sso\Domain\Exceptions\UnknownSsoClientException;
use Modules\Sso\Domain\Ports\SsoClientRepository;

final class SsoClientRegistry
{
    public function __construct(
        private readonly SsoClientRepository $repository,
        private readonly string $sharedSecret,
    ) {}

    public function get(string $clientId): SsoClient
    {
        if ($this->sharedSecret === '') {
            throw new UnknownSsoClientException($clientId);
        }

        $definition = $this->repository->findEnabledByClientId($clientId);

        if ($definition === null) {
            throw new UnknownSsoClientException($clientId);
        }

        return new SsoClient(
            $definition->clientId,
            $this->sharedSecret,
            $definition->redirectUris,
            $definition->redirectUriPatterns,
        );
    }

    public function has(string $clientId): bool
    {
        if ($this->sharedSecret === '') {
            return false;
        }

        return $this->repository->findEnabledByClientId($clientId) !== null;
    }
}
