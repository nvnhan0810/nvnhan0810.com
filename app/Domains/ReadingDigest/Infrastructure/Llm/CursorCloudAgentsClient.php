<?php

namespace App\Domains\ReadingDigest\Infrastructure\Llm;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CursorCloudAgentsClient
{
    private const AGENT_CACHE_KEY = 'reading_digest:cursor_cloud_agent_id';

    private const TERMINAL_STATUSES = ['FINISHED', 'FAILED', 'CANCELLED', 'ERROR'];

    private static ?string $agentId = null;

    public function apiKey(): ?string
    {
        $key = config('reading-digest.cursor_api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function chatCompletions(array $payload, int $timeout = 90): Response
    {
        if (! $this->isConfigured()) {
            return $this->errorResponse(401, 'Cursor API key not configured');
        }

        try {
            $prompt = $this->formatMessages($payload['messages'] ?? []);
            $modelId = (string) ($payload['model'] ?? config('reading-digest.ranking_model', 'composer-2.5'));
            $agentId = $this->rememberedAgentId();

            if (is_string($agentId) && $agentId !== '' && $this->agentExists($agentId)) {
                $runId = $this->createFollowUpRun($agentId, $prompt, $modelId);
            } else {
                $this->forgetAgentId();
                [$agentId, $runId] = $this->createAgent($prompt, $modelId);
                $this->rememberAgentId($agentId);
            }

            $result = $this->waitForRunResult($agentId, $runId, $timeout);

            if ($result === null) {
                return $this->errorResponse(504, 'Cursor Cloud Agent run timed out');
            }

            return $this->openAiResponse($result);
        } catch (\Throwable $e) {
            Log::warning('Cursor Cloud Agents chat failed', ['message' => $e->getMessage()]);

            return $this->errorResponse(502, $e->getMessage());
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createAgent(string $prompt, string $modelId): array
    {
        $response = $this->request()
            ->post('https://api.cursor.com/v1/agents', [
                'prompt' => ['text' => $prompt],
                'model' => ['id' => $modelId],
            ])
            ->throw();

        return [
            (string) $response->json('agent.id'),
            (string) $response->json('run.id'),
        ];
    }

    private function createFollowUpRun(string $agentId, string $prompt, string $modelId): string
    {
        $attempts = 0;

        while ($attempts < 30) {
            $response = $this->request()->post("https://api.cursor.com/v1/agents/{$agentId}/runs", [
                'prompt' => ['text' => $prompt],
                'model' => ['id' => $modelId],
            ]);

            if ($response->status() === 409) {
                usleep(500_000);
                $attempts++;

                continue;
            }

            $response->throw();

            return (string) $response->json('run.id');
        }

        throw new \RuntimeException('Cursor Cloud Agent is busy');
    }

    private function waitForRunResult(string $agentId, string $runId, int $timeout): ?string
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $response = $this->request()
                ->get("https://api.cursor.com/v1/agents/{$agentId}/runs/{$runId}")
                ->throw();

            $status = (string) $response->json('status', '');

            if (in_array($status, self::TERMINAL_STATUSES, true)) {
                if ($status !== 'FINISHED') {
                    throw new \RuntimeException("Cursor Cloud Agent run {$status}");
                }

                $result = $response->json('result');

                return is_string($result) ? trim($result) : null;
            }

            usleep(1_000_000);
        }

        return null;
    }

    private function agentExists(string $agentId): bool
    {
        $response = $this->request()->get("https://api.cursor.com/v1/agents/{$agentId}");

        if ($response->status() === 404) {
            return false;
        }

        $response->throw();

        return true;
    }

    /**
     * @param  array<int, array{role?: string, content?: string}>  $messages
     */
    private function formatMessages(array $messages): string
    {
        return collect($messages)
            ->map(function (array $message): string {
                $role = strtoupper((string) ($message['role'] ?? 'user'));

                return "[{$role}]\n".(string) ($message['content'] ?? '');
            })
            ->implode("\n\n");
    }

    private function rememberedAgentId(): ?string
    {
        if (self::$agentId !== null) {
            return self::$agentId;
        }

        try {
            $cached = Cache::get(self::AGENT_CACHE_KEY);

            return is_string($cached) && $cached !== '' ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function rememberAgentId(string $agentId): void
    {
        self::$agentId = $agentId;

        try {
            Cache::put(self::AGENT_CACHE_KEY, $agentId);
        } catch (\Throwable) {
        }
    }

    private function forgetAgentId(): void
    {
        self::$agentId = null;

        try {
            Cache::forget(self::AGENT_CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->apiKey(), '')
            ->acceptJson()
            ->asJson()
            ->timeout(120);
    }

    private function openAiResponse(string $content): Response
    {
        return new Response(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $content,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    private function errorResponse(int $status, string $message): Response
    {
        return new Response(new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => $message],
        ], JSON_THROW_ON_ERROR)));
    }
}
