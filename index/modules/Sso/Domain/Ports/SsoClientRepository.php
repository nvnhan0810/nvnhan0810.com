<?php

namespace Modules\Sso\Domain\Ports;

use Modules\Sso\Domain\SsoClientDefinition;

interface SsoClientRepository
{
    /**
     * @return list<SsoClientDefinition>
     */
    public function all(): array;

    public function findById(string $id): ?SsoClientDefinition;

    public function findByClientId(string $clientId): ?SsoClientDefinition;

    public function findEnabledByClientId(string $clientId): ?SsoClientDefinition;

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $redirectUriPatterns
     */
    public function create(
        string $clientId,
        string $name,
        ?string $domain,
        array $redirectUris,
        array $redirectUriPatterns,
        bool $enabled,
    ): SsoClientDefinition;

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $redirectUriPatterns
     */
    public function update(
        string $id,
        string $clientId,
        string $name,
        ?string $domain,
        array $redirectUris,
        array $redirectUriPatterns,
        bool $enabled,
    ): SsoClientDefinition;

    public function delete(string $id): void;

    public function clientIdExists(string $clientId, ?string $exceptId = null): bool;
}
