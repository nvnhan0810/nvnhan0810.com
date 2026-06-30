<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\SendDigestHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDigestTelegramJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $digestRunId) {}

    public function handle(SendDigestHandler $handler): void
    {
        Log::warning('[ReadingDigest] SendDigestTelegramJob: started', [
            'digest_run_id' => $this->digestRunId,
            'job_id' => $this->job?->getJobId(),
            'queue' => $this->job?->getQueue(),
            'attempt' => $this->attempts(),
        ]);

        try {
            $handler->handle($this->digestRunId);

            Log::warning('[ReadingDigest] SendDigestTelegramJob: completed', [
                'digest_run_id' => $this->digestRunId,
            ]);
        } catch (Throwable $e) {
            Log::warning('[ReadingDigest] SendDigestTelegramJob: failed', [
                'digest_run_id' => $this->digestRunId,
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
        Log::warning('[ReadingDigest] SendDigestTelegramJob: permanently failed', [
            'digest_run_id' => $this->digestRunId,
            'error' => $exception?->getMessage(),
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
