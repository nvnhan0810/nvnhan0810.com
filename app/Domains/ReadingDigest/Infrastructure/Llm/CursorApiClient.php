<?php

namespace App\Domains\ReadingDigest\Infrastructure\Llm;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CursorApiClient
{
    public function apiKey(): ?string
    {
        $key = config('reading-digest.cursor_api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('reading-digest.cursor_api_base_url', 'https://api.cursor.com/v1'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function chatCompletions(array $payload, int $timeout = 90): Response
    {
        return Http::withToken($this->apiKey())
            ->timeout($timeout)
            ->post($this->baseUrl().'/chat/completions', $payload);
    }

    public function embeddings(array $payload, int $timeout = 60): Response
    {
        return Http::withToken($this->apiKey())
            ->timeout($timeout)
            ->post($this->baseUrl().'/embeddings', $payload);
    }
}
