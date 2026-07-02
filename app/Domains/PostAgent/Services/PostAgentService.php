<?php

namespace App\Domains\PostAgent\Services;

use App\Domains\PostAgent\Infrastructure\Llm\CursorAgentsClient;
use App\Domains\PostAgent\Infrastructure\Llm\PostAgentCancelledException;
use App\Models\PostAgentSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PostAgentService
{
    private const ACTIVE_RUN_CACHE_TTL = 300;

    public function __construct(
        private readonly CursorAgentsClient $cursorClient,
        private readonly PostContextBuilder $contextBuilder,
        private readonly PostEditParser $editParser,
    ) {}

    public function isConfigured(): bool
    {
        return $this->cursorClient->isConfigured();
    }

    /**
     * @param  array{message: string, session_id?: string|null, post_id?: int|null, context: array<string, mixed>}  $input
     * @return array{session_id: string, reply: string, edits: array{locales: array<string, string>, source_urls: array<string, string>}, messages: array<int, array<string, mixed>>}
     */
    public function chat(int $userId, array $input): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Cursor API key chưa được cấu hình. Thêm CURSOR_API_KEY vào .env');
        }

        $session = $this->resolveSession(
            $userId,
            $input['session_id'] ?? null,
            $input['post_id'] ?? null,
        );

        $history = $this->conversationHistory($session->messages ?? []);
        $prompt = $this->contextBuilder->buildPrompt(
            (string) $input['message'],
            $input['context'],
            $history,
        );

        $this->clearCancellation($userId);

        try {
            $run = $this->cursorClient->run(
                $prompt,
                $session->cursor_agent_id,
                shouldCancel: fn () => $this->isCancellationRequested($userId),
                onRunStarted: fn (string $agentId, string $runId) => $this->rememberActiveRun(
                    $userId,
                    $session->id,
                    $agentId,
                    $runId,
                ),
            );
        } catch (PostAgentCancelledException $e) {
            $this->clearActiveRun($userId);

            throw $e;
        } catch (\Throwable $e) {
            $this->clearActiveRun($userId);

            Log::warning('Post agent chat failed', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->clearActiveRun($userId);

        $parsed = $this->editParser->parse($run['result']);

        $messages = $session->messages ?? [];
        $messages[] = [
            'role' => 'user',
            'content' => (string) $input['message'],
            'created_at' => now()->toIso8601String(),
        ];
        $messages[] = [
            'role' => 'assistant',
            'content' => $parsed['reply'],
            'edits' => $parsed['edits'],
            'created_at' => now()->toIso8601String(),
        ];

        $session->update([
            'cursor_agent_id' => $run['agent_id'],
            'post_id' => $input['post_id'] ?? $session->post_id,
            'messages' => $messages,
        ]);

        return [
            'session_id' => $session->id,
            'reply' => $parsed['reply'],
            'edits' => $parsed['edits'],
            'messages' => $messages,
        ];
    }

    public function cancel(int $userId): void
    {
        Cache::put($this->cancelCacheKey($userId), true, self::ACTIVE_RUN_CACHE_TTL);

        $activeRun = Cache::get($this->activeRunCacheKey($userId));

        if (! is_array($activeRun)) {
            return;
        }

        $agentId = (string) ($activeRun['agent_id'] ?? '');
        $runId = (string) ($activeRun['run_id'] ?? '');

        if ($agentId === '' || $runId === '') {
            return;
        }

        try {
            $this->cursorClient->cancelRun($agentId, $runId);
        } catch (\Throwable $e) {
            Log::info('Post agent cancel request failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{session_id: string, messages: array<int, array<string, mixed>>}
     */
    public function getSessionForPost(int $userId, ?int $postId): array
    {
        if ($postId === null) {
            return [
                'session_id' => '',
                'messages' => [],
            ];
        }

        $session = PostAgentSession::query()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->latest('updated_at')
            ->first();

        if (! $session) {
            return [
                'session_id' => '',
                'messages' => [],
            ];
        }

        return [
            'session_id' => $session->id,
            'messages' => $session->messages ?? [],
        ];
    }

    private function resolveSession(int $userId, ?string $sessionId, ?int $postId): PostAgentSession
    {
        if (is_string($sessionId) && $sessionId !== '') {
            $session = PostAgentSession::query()
                ->where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if ($session) {
                return $session;
            }
        }

        if ($postId !== null) {
            $existing = PostAgentSession::query()
                ->where('user_id', $userId)
                ->where('post_id', $postId)
                ->latest('updated_at')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return PostAgentSession::create([
            'id' => is_string($sessionId) && $sessionId !== ''
                ? $sessionId
                : (string) Str::uuid(),
            'user_id' => $userId,
            'post_id' => $postId,
            'messages' => [],
        ]);
    }

    private function rememberActiveRun(
        int $userId,
        string $sessionId,
        string $agentId,
        string $runId,
    ): void {
        Cache::put($this->activeRunCacheKey($userId), [
            'session_id' => $sessionId,
            'agent_id' => $agentId,
            'run_id' => $runId,
        ], self::ACTIVE_RUN_CACHE_TTL);
    }

    private function clearActiveRun(int $userId): void
    {
        Cache::forget($this->activeRunCacheKey($userId));
        Cache::forget($this->cancelCacheKey($userId));
    }

    private function clearCancellation(int $userId): void
    {
        Cache::forget($this->cancelCacheKey($userId));
    }

    private function isCancellationRequested(int $userId): bool
    {
        return Cache::get($this->cancelCacheKey($userId)) === true;
    }

    private function activeRunCacheKey(int $userId): string
    {
        return "post_agent:active_run:{$userId}";
    }

    private function cancelCacheKey(int $userId): string
    {
        return "post_agent:cancelled:{$userId}";
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function conversationHistory(array $messages): array
    {
        return collect($messages)
            ->take(-12)
            ->map(fn (array $message): array => [
                'role' => (string) ($message['role'] ?? 'user'),
                'content' => (string) ($message['content'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
