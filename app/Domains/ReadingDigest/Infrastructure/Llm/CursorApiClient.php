<?php

namespace App\Domains\ReadingDigest\Infrastructure\Llm;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CursorApiClient
{
    private static bool $proxyChatUnavailable = false;

    private static bool $proxyEmbeddingsUnavailable = false;

    private static bool $embeddingsUnsupportedLogged = false;

    public function __construct(
        private readonly CursorCloudAgentsClient $cloudAgents,
    ) {}

    public function apiKey(): ?string
    {
        $key = config('reading-digest.cursor_api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('reading-digest.cursor_api_base_url', 'http://127.0.0.1:3001/v1'), '/');
    }

    public function driver(): string
    {
        return (string) config('reading-digest.llm_driver', 'cloud_agents');
    }

    public function isConfigured(): bool
    {
        if ($this->driver() === 'cloud_agents') {
            return $this->cloudAgents->isConfigured();
        }

        return $this->baseUrl() !== '';
    }

    public function chatCompletions(array $payload, int $timeout = 90): Response
    {
        if ($this->driver() === 'cloud_agents') {
            return $this->cloudAgents->chatCompletions($payload, $timeout);
        }

        return $this->proxyChatCompletions($payload, $timeout);
    }

    public function embeddings(array $payload, int $timeout = 60): Response
    {
        if ($this->driver() === 'cloud_agents') {
            if (! self::$embeddingsUnsupportedLogged) {
                self::$embeddingsUnsupportedLogged = true;
                Log::info('Cursor Cloud Agents does not support embeddings; retrieval will use taxonomy and behavior only');
            }

            return $this->unavailableResponse();
        }

        return $this->proxyEmbeddings($payload, $timeout);
    }

    private function proxyChatCompletions(array $payload, int $timeout): Response
    {
        if (self::$proxyChatUnavailable) {
            return $this->unavailableResponse();
        }

        $request = Http::timeout($timeout);

        if ($this->apiKey() !== null) {
            $request = $request->withToken($this->apiKey());
        }

        $response = $request->post($this->baseUrl().'/chat/completions', $payload);

        if ($response->status() === 404) {
            self::$proxyChatUnavailable = true;
            Log::warning('Cursor proxy chat/completions endpoint not found; enrichment and ranking will use fallbacks', [
                'endpoint' => $this->baseUrl().'/chat/completions',
                'hint' => 'Run cursor-brain locally or set DIGEST_LLM_DRIVER=cloud_agents to use Cursor Cloud Agents API.',
            ]);
        }

        return $response;
    }

    private function proxyEmbeddings(array $payload, int $timeout): Response
    {
        if (self::$proxyEmbeddingsUnavailable) {
            return $this->unavailableResponse();
        }

        $request = Http::timeout($timeout);

        if ($this->apiKey() !== null) {
            $request = $request->withToken($this->apiKey());
        }

        $response = $request->post($this->baseUrl().'/embeddings', $payload);

        if (in_array($response->status(), [404, 501], true)) {
            self::$proxyEmbeddingsUnavailable = true;
            Log::info('Cursor proxy does not support embeddings; retrieval will use taxonomy and behavior only', [
                'endpoint' => $this->baseUrl().'/embeddings',
            ]);
        }

        return $response;
    }

    private function unavailableResponse(): Response
    {
        return new Response(new \GuzzleHttp\Psr7\Response(404, [], 'LLM endpoint unavailable'));
    }
}
