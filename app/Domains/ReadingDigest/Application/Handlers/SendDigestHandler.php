<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Telegram\TelegramDigestNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDigestHandler
{
    public function __construct(
        private readonly TelegramDigestNotifier $notifier,
    ) {}

    public function handle(string $digestRunId): void
    {
        Log::warning('[ReadingDigest] SendDigestHandler: started', [
            'digest_run_id' => $digestRunId,
        ]);

        try {
            $run = DigestRunModel::query()->findOrFail($digestRunId);

            Log::warning('[ReadingDigest] SendDigestHandler: digest run loaded', [
                'digest_run_id' => $run->id,
                'user_id' => $run->user_id,
                'status' => $run->status,
                'run_date' => $run->run_date?->toIso8601String(),
                'items_count' => $run->items()->count(),
                'telegram_sent_at' => $run->telegram_sent_at?->toIso8601String(),
            ]);

            $sent = $this->notifier->send($run);

            Log::warning('[ReadingDigest] SendDigestHandler: notifier result', [
                'digest_run_id' => $digestRunId,
                'sent' => $sent,
            ]);

            if ($sent) {
                $run->update(['telegram_sent_at' => now()]);

                Log::warning('[ReadingDigest] SendDigestHandler: telegram_sent_at updated', [
                    'digest_run_id' => $digestRunId,
                ]);
            } else {
                Log::warning('[ReadingDigest] SendDigestHandler: Telegram not sent (notifier returned false)', [
                    'digest_run_id' => $digestRunId,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[ReadingDigest] SendDigestHandler: failed', [
                'digest_run_id' => $digestRunId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
