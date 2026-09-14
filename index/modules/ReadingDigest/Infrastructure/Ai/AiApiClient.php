<?php

namespace Modules\ReadingDigest\Infrastructure\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for ai.nvnhan0810.com (embed + enrich).
 */
final class AiApiClient
{
    public const PATH_EMBED = '/api/embed';

    public const PATH_ENRICH = '/api/enrich';

    public function baseUrl(): string
    {
        return rtrim((string) config('reading-digest.ai.base_url', ''), '/');
    }

    public function apiKey(): ?string
    {
        $key = config('reading-digest.ai.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== null;
    }

    public function embed(string $text, int $timeout = 0): Response
    {
        return $this->post(self::PATH_EMBED, ['text' => $text], $timeout);
    }

    public function enrich(string $text, int $timeout = 0): Response
    {
        return $this->post(self::PATH_ENRICH, ['text' => $text], $timeout);
    }

    private function post(string $path, array $payload, int $timeout): Response
    {
        if (! $this->isConfigured()) {
            return $this->errorResponse(401, 'DIGEST_AI_BASE_URL / DIGEST_AI_API_KEY is not configured');
        }

        $seconds = $timeout > 0
            ? $timeout
            : (int) config('reading-digest.ai.timeout', 60);

        return Http::timeout($seconds)
            ->withHeaders(['X-Api-Key' => $this->apiKey()])
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().$path, $payload);
    }

    private function errorResponse(int $status, string $message): Response
    {
        Log::warning('Reading Digest AI API unavailable', ['message' => $message]);

        return new Response(new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => $message],
        ], JSON_THROW_ON_ERROR)));
    }
}
