<?php

namespace Modules\Sso\Application\Handler;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Sso\Application\DTOs\SsoIntent;
use Modules\Sso\Application\DTOs\StoredAuthorizationCode;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;
use Modules\Sso\Domain\SsoClientRegistry;

final class IssueAuthorizationCodeHandler
{
    public function __construct(
        private readonly SsoClientRegistry $clients,
        private readonly AuthorizationCodeStore $codes,
    ) {}

    public function buildRedirectUrl(SsoIntent $intent, User $user): string
    {
        $client = $this->clients->get($intent->clientId);

        if (! $client->acceptsRedirectUri($intent->redirectUri)) {
            abort(400, 'Invalid redirect_uri');
        }

        $code = Str::random(64);
        $ttl = (int) config('sso.code_ttl_seconds', 120);

        $this->codes->put($code, new StoredAuthorizationCode(
            $intent->clientId,
            $intent->redirectUri,
            (int) $user->id,
            (string) $user->email,
            (string) $user->name,
            is_string($user->avatar ?? null) ? $user->avatar : null,
        ), $ttl);

        $query = http_build_query([
            'code' => $code,
            'state' => $intent->state,
        ]);

        $separator = str_contains($intent->redirectUri, '?') ? '&' : '?';

        return $intent->redirectUri.$separator.$query;
    }
}
