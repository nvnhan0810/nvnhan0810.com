<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Best-effort cancel of pending delayed SendPomodoroPhasePushJob rows.
 * Deliver still guards by session_uuid if a row races past cancel.
 */
final class CancelPomodoroPhaseJobs
{
    public function execute(?string $sessionUuid): void
    {
        if ($sessionUuid === null || $sessionUuid === '') {
            return;
        }

        try {
            $driver = (string) config('queue.default', 'redis');
            $deleted = match ($driver) {
                'redis' => $this->cancelRedis($sessionUuid),
                'database' => $this->cancelDatabase($sessionUuid),
                default => 0,
            };

            if ($deleted > 0) {
                Log::info('web-push.pomodoro.jobs_cancelled', [
                    'session_uuid' => $sessionUuid,
                    'driver' => $driver,
                    'deleted' => $deleted,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('web-push.pomodoro.jobs_cancel_failed', [
                'session_uuid' => $sessionUuid,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function cancelDatabase(string $sessionUuid): int
    {
        return (int) DB::table('jobs')
            ->where('payload', 'like', '%'.$sessionUuid.'%')
            ->delete();
    }

    private function cancelRedis(string $sessionUuid): int
    {
        $connection = (string) config('queue.connections.redis.connection', 'default');
        $queue = (string) config('queue.connections.redis.queue', 'default');
        $redis = Redis::connection($connection);
        $base = 'queues:'.$queue;
        $deleted = 0;

        foreach ([$base.':delayed', $base.':reserved'] as $zset) {
            /** @var list<string> $payloads */
            $payloads = $redis->zrange($zset, 0, -1);
            foreach ($payloads as $payload) {
                if (! is_string($payload) || ! str_contains($payload, $sessionUuid)) {
                    continue;
                }
                $deleted += (int) $redis->zrem($zset, $payload);
            }
        }

        $len = (int) $redis->llen($base);
        for ($i = 0; $i < $len; $i++) {
            $payload = $redis->lindex($base, $i);
            if (! is_string($payload) || ! str_contains($payload, $sessionUuid)) {
                continue;
            }
            $deleted += (int) $redis->lrem($base, 1, $payload);
        }

        return $deleted;
    }
}
