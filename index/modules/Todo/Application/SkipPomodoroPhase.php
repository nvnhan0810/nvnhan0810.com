<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Str;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class SkipPomodoroPhase
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly AdvancePomodoroPhase $advancePomodoroPhase,
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
    public function execute(int $userId): array
    {
        $existing = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);
        $previousUuid = $existing->sessionUuid;
        if ($previousUuid !== null) {
            $this->cancelPomodoroPhaseJobs->execute($previousUuid);
        }

        $keepRunning = $existing->isRunning;
        $base = new PomodoroState(
            userId: $existing->userId,
            focusMinutes: $existing->focusMinutes,
            shortBreakMinutes: $existing->shortBreakMinutes,
            sessionsBeforeLongBreak: $existing->sessionsBeforeLongBreak,
            longBreakMinutes: $existing->longBreakMinutes,
            phase: $existing->phase,
            remainingMs: $existing->remainingMs,
            endsAt: $existing->endsAt,
            focusCount: $existing->focusCount,
            activeTodoId: $existing->activeTodoId,
            isRunning: $keepRunning,
            clientUpdatedAt: $existing->clientUpdatedAt,
            sessionUuid: $existing->sessionUuid,
            lastFocusedAt: $existing->lastFocusedAt,
        );

        $advanced = $this->advancePomodoroPhase->advance($base);
        $nowMs = PomodoroState::nowMs();
        $sessionUuid = $advanced->isRunning ? (string) Str::uuid() : null;

        $next = new PomodoroState(
            userId: $advanced->userId,
            focusMinutes: $advanced->focusMinutes,
            shortBreakMinutes: $advanced->shortBreakMinutes,
            sessionsBeforeLongBreak: $advanced->sessionsBeforeLongBreak,
            longBreakMinutes: $advanced->longBreakMinutes,
            phase: $advanced->phase,
            remainingMs: $advanced->remainingMs,
            endsAt: $advanced->endsAt,
            focusCount: $advanced->focusCount,
            activeTodoId: $advanced->activeTodoId,
            isRunning: $advanced->isRunning,
            clientUpdatedAt: $nowMs,
            sessionUuid: $sessionUuid,
            lastFocusedAt: $advanced->lastFocusedAt,
        );

        // If was paused, advance leaves endsAt null and isRunning false — still move phase.
        if (! $keepRunning) {
            $next = new PomodoroState(
                userId: $next->userId,
                focusMinutes: $next->focusMinutes,
                shortBreakMinutes: $next->shortBreakMinutes,
                sessionsBeforeLongBreak: $next->sessionsBeforeLongBreak,
                longBreakMinutes: $next->longBreakMinutes,
                phase: $next->phase,
                remainingMs: $next->remainingMs,
                endsAt: null,
                focusCount: $next->focusCount,
                activeTodoId: $next->activeTodoId,
                isRunning: false,
                clientUpdatedAt: $nowMs,
                sessionUuid: null,
                lastFocusedAt: $next->lastFocusedAt,
            );
        }

        $saved = $this->repository->save($next);
        PomodoroStreamVersion::bump($userId);

        if ($saved->isRunning && $saved->endsAt !== null && $saved->sessionUuid !== null) {
            $this->schedulePomodoroPhasePush->execute(
                $userId,
                $saved->sessionUuid,
                $saved->endsAt,
                true,
                $saved->phase,
                $previousUuid,
            );
        }

        return $saved->toPayload(PomodoroStreamVersion::current($userId));
    }
}
