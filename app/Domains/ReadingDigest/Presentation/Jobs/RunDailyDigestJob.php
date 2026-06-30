<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\FetchAllSourcesHandler;
use App\Domains\ReadingDigest\Application\Handlers\RunDailyDigestHandler;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDailyDigestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(
        FetchAllSourcesHandler $fetchAllSources,
        RunDailyDigestHandler $digestHandler,
    ): void {
        Log::warning('[ReadingDigest] RunDailyDigestJob: started', [
            'job_id' => $this->job?->getJobId(),
            'queue' => $this->job?->getQueue(),
            'attempt' => $this->attempts(),
        ]);

        try {
            Log::warning('[ReadingDigest] RunDailyDigestJob: fetching all sources');
            $fetchStats = $fetchAllSources->handle();
            Log::warning('[ReadingDigest] RunDailyDigestJob: fetch completed', [
                'fetch_stats' => $fetchStats,
            ]);

            $user = User::query()->orderBy('id')->first();
            if (! $user) {
                Log::warning('[ReadingDigest] RunDailyDigestJob: aborted — no user found');

                return;
            }

            Log::warning('[ReadingDigest] RunDailyDigestJob: running digest', [
                'user_id' => $user->id,
            ]);
            $run = $digestHandler->handle($user->id);

            $run->update([
                'stats' => array_merge($run->stats ?? [], [
                    'fetch' => $fetchStats,
                ]),
            ]);

            Log::warning('[ReadingDigest] RunDailyDigestJob: completed', [
                'digest_run_id' => $run->id,
                'status' => $run->status,
                'stats' => $run->stats,
            ]);
        } catch (Throwable $e) {
            Log::warning('[ReadingDigest] RunDailyDigestJob: failed', [
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('[ReadingDigest] RunDailyDigestJob: permanently failed', [
            'error' => $exception?->getMessage(),
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
