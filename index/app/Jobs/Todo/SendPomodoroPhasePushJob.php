<?php

namespace App\Jobs\Todo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Todo\Application\DeliverPomodoroPhasePush;
use Modules\Todo\Domain\PomodoroDefaults;

final class SendPomodoroPhasePushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $userId;

    public string $sessionUuid;

    public int $expectedEndsAtMs;

    /**
     * Default keeps delayed jobs serialized before fromPhase was added runnable.
     */
    public string $fromPhase = PomodoroDefaults::PHASE_FOCUS;

    public function __construct(
        int $userId,
        string $sessionUuid,
        int $expectedEndsAtMs,
        string $fromPhase = PomodoroDefaults::PHASE_FOCUS,
    ) {
        $this->userId = $userId;
        $this->sessionUuid = $sessionUuid;
        $this->expectedEndsAtMs = $expectedEndsAtMs;
        $this->fromPhase = in_array($fromPhase, PomodoroDefaults::PHASES, true)
            ? $fromPhase
            : PomodoroDefaults::PHASE_FOCUS;
    }

    public function handle(DeliverPomodoroPhasePush $deliver): void
    {
        $deliver->execute(
            $this->userId,
            $this->sessionUuid,
            $this->expectedEndsAtMs,
            $this->fromPhase,
        );
    }
}
