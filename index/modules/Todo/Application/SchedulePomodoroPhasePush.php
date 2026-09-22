<?php

namespace Modules\Todo\Application;

use App\Jobs\Todo\SendPomodoroPhasePushJob;
use Carbon\Carbon;
use Modules\Todo\Domain\Ports\WebPushSender;

final class SchedulePomodoroPhasePush
{
    public function __construct(
        private readonly WebPushSender $sender,
    ) {}

    public function execute(int $userId, ?int $endsAtMs, bool $isRunning): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        if (! $isRunning || $endsAtMs === null || $endsAtMs <= 0) {
            return;
        }

        $nowMs = (int) floor(microtime(true) * 1000);
        $delaySeconds = max(0, (int) ceil(($endsAtMs - $nowMs) / 1000));

        SendPomodoroPhasePushJob::dispatch($userId, $endsAtMs)
            ->delay(Carbon::now()->addSeconds($delaySeconds));
    }
}
