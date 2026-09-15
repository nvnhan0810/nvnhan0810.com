<?php

namespace Modules\Sso\Application\DTOs;

final class SsoIntent
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly string $state,
    ) {}

    /** @return array{client_id: string, redirect_uri: string, state: string} */
    public function toSession(): array
    {
        return [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $this->state,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromSession(array $data): ?self
    {
        $clientId = $data['client_id'] ?? null;
        $redirectUri = $data['redirect_uri'] ?? null;
        $state = $data['state'] ?? null;

        if (! is_string($clientId) || ! is_string($redirectUri) || ! is_string($state)) {
            return null;
        }

        if ($clientId === '' || $redirectUri === '') {
            return null;
        }

        return new self($clientId, $redirectUri, $state);
    }
}
