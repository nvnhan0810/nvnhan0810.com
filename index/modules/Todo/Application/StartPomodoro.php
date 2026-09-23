<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Str;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class StartPomodoro
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly SchedulePomodoroPhasePush $schedulePomodoroPhasePush,
        private readonly CancelPomodoroPhaseJobs $cancelPomodoroPhaseJobs,
    ) {}

    /**
     * @return array{
     *   settings: array{focusMinutes: int, shortBreakMinutes: int, sessionsBeforeLongBreak: int, longBreakMinutes: int},
     *   runtime: array{phase: string, remainingMs: int, endsAt: int|null, focusCount: int, activeTodoId: int|null, isRunning: bool, updatedAt: int, sessionUuid: string|null},
     *   version: int
     * }
     */
    public function execute(int $userId, ?int $activeTodoId = null): array
    {
        $existing = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);
        $nowMs = PomodoroState::nowMs();

        $remainingMs = $existing->isRunning && $existing->endsAt !== null
            ? max(0, $existing->endsAt - $nowMs)
            : max(0, $existing->remainingMs);

        if ($remainingMs <= 0) {
            return $existing->toPayload(PomodoroStreamVersion::current($userId));
        }

        if ($existing->isRunning && $existing->sessionUuid !== null && $existing->endsAt !== null) {
            // Already running — optional active todo update only.
            if ($activeTodoId !== null && $activeTodoId !== $existing->activeTodoId) {
                $updated = new PomodoroState(
                    userId: $existing->userId,
                    focusMinutes: $existing->focusMinutes,
                    shortBreakMinutes: $existing->shortBreakMinutes,
                    sessionsBeforeLongBreak: $existing->sessionsBeforeLongBreak,
                    longBreakMinutes: $existing->longBreakMinutes,
                    phase: $existing->phase,
                    remainingMs: $remainingMs,
                    endsAt: $existing->endsAt,
                    focusCount: $existing->focusCount,
                    activeTodoId: $activeTodoId,
                    isRunning: true,
                    clientUpdatedAt: $nowMs,
                    sessionUuid: $existing->sessionUuid,
                    lastFocusedAt: $existing->lastFocusedAt,
                );
                $saved = $this->repository->save($updated);
                PomodoroStreamVersion::bump($userId);

                return $saved->toPayload(PomodoroStreamVersion::current($userId));
            }

            return $existing->toPayload(PomodoroStreamVersion::current($userId));
        }

        $previousUuid = $existing->sessionUuid;
        if ($previousUuid !== null) {
            $this->cancelPomodoroPhaseJobs->execute($previousUuid);
        }

        $sessionUuid = (string) Str::uuid();
        $endsAt = $nowMs + $remainingMs;
        $next = new PomodoroState(
            userId: $existing->userId,
            focusMinutes: $existing->focusMinutes,
            shortBreakMinutes: $existing->shortBreakMinutes,
            sessionsBeforeLongBreak: $existing->sessionsBeforeLongBreak,
            longBreakMinutes: $existing->longBreakMinutes,
            phase: $existing->phase,
            remainingMs: $remainingMs,
            endsAt: $endsAt,
            focusCount: $existing->focusCount,
            activeTodoId: $activeTodoId ?? $existing->activeTodoId,
            isRunning: true,
            clientUpdatedAt: $nowMs,
            sessionUuid: $sessionUuid,
            lastFocusedAt: $existing->lastFocusedAt,
        );

        $saved = $this->repository->save($next);
        PomodoroStreamVersion::bump($userId);

        $this->schedulePomodoroPhasePush->execute(
            $userId,
            $sessionUuid,
            $endsAt,
            true,
            $saved->phase,
            $previousUuid,
        );

        return $saved->toPayload(PomodoroStreamVersion::current($userId));
    }
}
