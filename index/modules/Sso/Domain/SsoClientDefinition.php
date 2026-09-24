<?php

namespace Modules\Sso\Domain;

final class SsoClientDefinition
{
    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $redirectUriPatterns
     */
    public function __construct(
        public readonly string $id,
        public readonly string $clientId,
        public readonly string $name,
        public readonly ?string $domain,
        public readonly array $redirectUris,
        public readonly array $redirectUriPatterns,
        public readonly bool $enabled,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     client_id: string,
     *     name: string,
     *     domain: string|null,
     *     redirect_uris: list<string>,
     *     redirect_uri_patterns: list<string>,
     *     enabled: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'name' => $this->name,
            'domain' => $this->domain,
            'redirect_uris' => $this->redirectUris,
            'redirect_uri_patterns' => $this->redirectUriPatterns,
            'enabled' => $this->enabled,
        ];
    }
}
