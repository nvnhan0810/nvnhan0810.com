<?php

namespace App\Domains\ReadingDigest\Infrastructure\Llm;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiApiClient
{
    public function apiKey(): ?string
    {
        $key = config('reading-digest.gemini.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('reading-digest.gemini.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function chatCompletions(array $payload, int $timeout = 90): Response
    {
        if (! $this->isConfigured()) {
            return $this->errorResponse(401, 'GEMINI_API_KEY is not configured');
        }

        return Http::timeout($timeout)
            ->withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/chat/completions', $payload);
    }

    public function embeddings(array $payload, int $timeout = 60): Response
    {
        if (! $this->isConfigured()) {
            return $this->errorResponse(401, 'GEMINI_API_KEY is not configured');
        }

        return Http::timeout($timeout)
            ->withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/embeddings', $payload);
    }

    private function errorResponse(int $status, string $message): Response
    {
        Log::warning('Gemini API unavailable', ['message' => $message]);

        return new Response(new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => $message],
        ], JSON_THROW_ON_ERROR)));
    }
}
