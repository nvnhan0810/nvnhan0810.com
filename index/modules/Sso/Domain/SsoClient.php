<?php

namespace Modules\Sso\Domain;

final class SsoClient
{
    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $redirectUriPatterns  PCRE patterns
     */
    public function __construct(
        public readonly string $id,
        private readonly string $secret,
        private readonly array $redirectUris,
        private readonly array $redirectUriPatterns,
    ) {}

    public function verifySecret(string $secret): bool
    {
        return hash_equals($this->secret, $secret);
    }

    public function acceptsRedirectUri(string $redirectUri): bool
    {
        if (in_array($redirectUri, $this->redirectUris, true)) {
            return true;
        }

        foreach ($this->redirectUriPatterns as $pattern) {
            if (@preg_match($pattern, $redirectUri) === 1) {
                return true;
            }
        }

        return false;
    }
}
