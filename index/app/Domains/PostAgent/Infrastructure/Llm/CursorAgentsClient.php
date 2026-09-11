<?php

namespace App\Domains\PostAgent\Infrastructure\Llm;

use Illuminate\Support\Facades\Http;

class CursorAgentsClient
{
    private const TERMINAL_STATUSES = ['FINISHED', 'FAILED', 'CANCELLED', 'ERROR'];

    public function apiKey(): ?string
    {
        $key = config('post-agent.cursor_api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @param  callable(): bool|null  $shouldCancel
     * @param  callable(string, string): void|null  $onRunStarted
     * @return array{agent_id: string, run_id: string, result: string}
     */
    public function run(
        string $prompt,
        ?string $agentId = null,
        ?string $modelId = null,
        ?int $timeout = null,
        ?callable $shouldCancel = null,
        ?callable $onRunStarted = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Cursor API key chưa được cấu hình');
        }

        $modelId = $modelId ?? (string) config('post-agent.model', 'auto');
        $timeout = $timeout ?? (int) config('post-agent.timeout', 120);

        if (is_string($agentId) && $agentId !== '' && $this->agentExists($agentId)) {
            $runId = $this->createFollowUpRun($agentId, $prompt, $modelId);
        } else {
            [$agentId, $runId] = $this->createAgent($prompt, $modelId);
        }

        if ($onRunStarted !== null) {
            $onRunStarted($agentId, $runId);
        }

        $result = $this->waitForRunResult($agentId, $runId, $timeout, $shouldCancel);

        if ($result === '__cancelled__') {
            throw new PostAgentCancelledException;
        }

        if ($result === null) {
            throw new \RuntimeException('Cursor agent phản hồi quá thời gian chờ');
        }

        return [
            'agent_id' => $agentId,
            'run_id' => $runId,
            'result' => $result,
        ];
    }

    public function cancelRun(string $agentId, string $runId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $response = $this->request()->post(
            "https://api.cursor.com/v1/agents/{$agentId}/runs/{$runId}/cancel"
        );

        if ($response->status() === 409) {
            return;
        }

        $response->throw();
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

        throw new \RuntimeException('Cursor agent đang bận, thử lại sau');
    }

    private function waitForRunResult(
        string $agentId,
        string $runId,
        int $timeout,
        ?callable $shouldCancel = null,
    ): ?string {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            if ($shouldCancel !== null && $shouldCancel()) {
                try {
                    $this->cancelRun($agentId, $runId);
                } catch (\Throwable) {
                }

                return '__cancelled__';
            }

            $response = $this->request()
                ->get("https://api.cursor.com/v1/agents/{$agentId}/runs/{$runId}")
                ->throw();

            $status = (string) $response->json('status', '');

            if (in_array($status, self::TERMINAL_STATUSES, true)) {
                if ($status === 'CANCELLED') {
                    return '__cancelled__';
                }

                if ($status !== 'FINISHED') {
                    throw new \RuntimeException("Cursor agent run {$status}");
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

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = max(120, (int) config('post-agent.timeout', 120));

        return Http::withBasicAuth($this->apiKey(), '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($timeout);
    }
}
