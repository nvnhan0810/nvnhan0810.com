<?php

namespace Modules\Sso\Infrastructure\Persistence;

use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientDefinition;
use RuntimeException;

final class EloquentSsoClientRepository implements SsoClientRepository
{
    public function all(): array
    {
        return EloquentSsoClient::query()
            ->orderBy('client_id')
            ->get()
            ->map(fn (EloquentSsoClient $row): SsoClientDefinition => $this->toDefinition($row))
            ->all();
    }

    public function findById(string $id): ?SsoClientDefinition
    {
        $row = EloquentSsoClient::query()->find($id);

        return $row === null ? null : $this->toDefinition($row);
    }

    public function findByClientId(string $clientId): ?SsoClientDefinition
    {
        $row = EloquentSsoClient::query()->where('client_id', $clientId)->first();

        return $row === null ? null : $this->toDefinition($row);
    }

    public function findEnabledByClientId(string $clientId): ?SsoClientDefinition
    {
        $row = EloquentSsoClient::query()
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first();

        return $row === null ? null : $this->toDefinition($row);
    }

    public function create(
        string $clientId,
        string $name,
        ?string $domain,
        array $redirectUris,
        array $redirectUriPatterns,
        bool $enabled,
    ): SsoClientDefinition {
        $row = EloquentSsoClient::query()->create([
            'client_id' => $clientId,
            'name' => $name,
            'domain' => $domain,
            'redirect_uris' => $redirectUris,
            'redirect_uri_patterns' => $redirectUriPatterns,
            'enabled' => $enabled,
        ]);

        return $this->toDefinition($row);
    }

    public function update(
        string $id,
        string $clientId,
        string $name,
        ?string $domain,
        array $redirectUris,
        array $redirectUriPatterns,
        bool $enabled,
    ): SsoClientDefinition {
        $row = EloquentSsoClient::query()->find($id);

        if ($row === null) {
            throw new RuntimeException(sprintf('SSO client [%s] not found.', $id));
        }

        $row->update([
            'client_id' => $clientId,
            'name' => $name,
            'domain' => $domain,
            'redirect_uris' => $redirectUris,
            'redirect_uri_patterns' => $redirectUriPatterns,
            'enabled' => $enabled,
        ]);

        return $this->toDefinition($row->fresh() ?? $row);
    }

    public function delete(string $id): void
    {
        EloquentSsoClient::query()->where('id', $id)->delete();
    }

    public function clientIdExists(string $clientId, ?string $exceptId = null): bool
    {
        $query = EloquentSsoClient::query()->where('client_id', $clientId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function toDefinition(EloquentSsoClient $row): SsoClientDefinition
    {
        return new SsoClientDefinition(
            (string) $row->id,
            (string) $row->client_id,
            (string) $row->name,
            $row->domain !== null && $row->domain !== '' ? (string) $row->domain : null,
            array_values(array_filter((array) $row->redirect_uris, fn ($uri) => is_string($uri) && $uri !== '')),
            array_values(array_filter((array) $row->redirect_uri_patterns, fn ($p) => is_string($p) && $p !== '')),
            (bool) $row->enabled,
        );
    }
}
