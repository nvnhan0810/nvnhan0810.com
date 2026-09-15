<?php

namespace Modules\Sso\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Modules\Sso\Application\DTOs\StoredAuthorizationCode;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;

final class CacheAuthorizationCodeStore implements AuthorizationCodeStore
{
    private const KEY_PREFIX = 'sso:auth_code:';

    public function put(string $code, StoredAuthorizationCode $payload, int $ttlSeconds): void
    {
        Cache::put(self::KEY_PREFIX.$code, [
            'client_id' => $payload->clientId,
            'redirect_uri' => $payload->redirectUri,
            'user_id' => $payload->userId,
            'email' => $payload->email,
            'name' => $payload->name,
            'avatar' => $payload->avatar,
        ], $ttlSeconds);
    }

    public function pull(string $code): ?StoredAuthorizationCode
    {
        /** @var array<string, mixed>|null $data */
        $data = Cache::pull(self::KEY_PREFIX.$code);

        if (! is_array($data)) {
            return null;
        }

        $clientId = $data['client_id'] ?? null;
        $redirectUri = $data['redirect_uri'] ?? null;
        $userId = $data['user_id'] ?? null;
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $avatar = $data['avatar'] ?? null;

        if (! is_string($clientId) || ! is_string($redirectUri) || ! is_int($userId)) {
            return null;
        }

        if (! is_string($email) || ! is_string($name)) {
            return null;
        }

        return new StoredAuthorizationCode(
            $clientId,
            $redirectUri,
            $userId,
            $email,
            $name,
            is_string($avatar) ? $avatar : null,
        );
    }
}
