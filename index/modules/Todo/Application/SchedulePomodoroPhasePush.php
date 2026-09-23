<?php

namespace Modules\Todo\Application;

use App\Jobs\Todo\SendPomodoroPhasePushJob;
use Carbon\Carbon;
use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\Ports\WebPushSender;

final class SchedulePomodoroPhasePush
{
    public function __construct(
        private readonly WebPushSender $sender,
        private readonly CancelPomodoroPhaseJobs $cancelPomodoroPhaseJobs,
    ) {}

    public function execute(
        int $userId,
        ?string $sessionUuid,
        ?int $endsAtMs,
        bool $isRunning,
        string $fromPhase = PomodoroDefaults::PHASE_FOCUS,
        ?string $previousSessionUuid = null,
    ): void {
        if ($previousSessionUuid !== null && $previousSessionUuid !== $sessionUuid) {
            $this->cancelPomodoroPhaseJobs->execute($previousSessionUuid);
        }

        if (! $this->sender->isConfigured()) {
            return;
        }

        if (! $isRunning || $endsAtMs === null || $endsAtMs <= 0 || $sessionUuid === null || $sessionUuid === '') {
            return;
        }

        $phase = in_array($fromPhase, PomodoroDefaults::PHASES, true)
            ? $fromPhase
            : PomodoroDefaults::PHASE_FOCUS;

        $nowMs = (int) floor(microtime(true) * 1000);
        $delaySeconds = max(0, (int) ceil(($endsAtMs - $nowMs) / 1000));

        SendPomodoroPhasePushJob::dispatch($userId, $sessionUuid, $endsAtMs, $phase)
            ->delay(Carbon::now()->addSeconds($delaySeconds));
    }
}
