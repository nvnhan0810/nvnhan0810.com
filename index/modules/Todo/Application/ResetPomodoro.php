<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class ResetPomodoro
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly CancelPomodoroPhaseJobs $cancelPomodoroPhaseJobs,
    ) {}

    /**
     * @return array{
     *   settings: array{focusMinutes: int, shortBreakMinutes: int, sessionsBeforeLongBreak: int, longBreakMinutes: int},
     *   runtime: array{phase: string, remainingMs: int, endsAt: int|null, focusCount: int, activeTodoId: int|null, isRunning: bool, updatedAt: int, sessionUuid: string|null},
     *   version: int
     * }
     */
    public function execute(int $userId): array
    {
        $existing = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);
        if ($existing->sessionUuid !== null) {
            $this->cancelPomodoroPhaseJobs->execute($existing->sessionUuid);
        }

        $nowMs = PomodoroState::nowMs();
        $next = new PomodoroState(
            userId: $existing->userId,
            focusMinutes: $existing->focusMinutes,
            shortBreakMinutes: $existing->shortBreakMinutes,
            sessionsBeforeLongBreak: $existing->sessionsBeforeLongBreak,
            longBreakMinutes: $existing->longBreakMinutes,
            phase: PomodoroDefaults::PHASE_FOCUS,
            remainingMs: $existing->focusMinutes * 60_000,
            endsAt: null,
            focusCount: 0,
            activeTodoId: null,
            isRunning: false,
            clientUpdatedAt: $nowMs,
            sessionUuid: null,
            lastFocusedAt: $existing->lastFocusedAt,
        );

        $saved = $this->repository->save($next);
        PomodoroStreamVersion::bump($userId);

        return $saved->toPayload(PomodoroStreamVersion::current($userId));
    }
}
