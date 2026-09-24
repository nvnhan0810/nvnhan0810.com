<?php

namespace Modules\Sso\Application\Command;

use Modules\Shared\Application\Command;

final class UpdateSsoClient implements Command
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
}
