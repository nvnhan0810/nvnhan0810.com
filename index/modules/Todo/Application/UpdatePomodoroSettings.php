<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Str;
use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class UpdatePomodoroSettings
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly SchedulePomodoroPhasePush $schedulePomodoroPhasePush,
        private readonly CancelPomodoroPhaseJobs $cancelPomodoroPhaseJobs,
        private readonly AdvancePomodoroPhase $advancePomodoroPhase,
    ) {}

    /**
     * @param  array{
     *   focusMinutes: int,
     *   shortBreakMinutes: int,
     *   sessionsBeforeLongBreak: int,
     *   longBreakMinutes: int
     * }  $settings
     * @return array{
     *   settings: array{focusMinutes: int, shortBreakMinutes: int, sessionsBeforeLongBreak: int, longBreakMinutes: int},
     *   runtime: array{phase: string, remainingMs: int, endsAt: int|null, focusCount: int, activeTodoId: int|null, isRunning: bool, updatedAt: int, sessionUuid: string|null},
     *   version: int
     * }
     */
    public function execute(int $userId, array $settings): array
    {
        $existing = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);
        $nowMs = PomodoroState::nowMs();

        $focusMinutes = $this->clamp($settings['focusMinutes'], 1, 180, PomodoroDefaults::FOCUS_MINUTES);
        $shortBreakMinutes = $this->clamp($settings['shortBreakMinutes'], 1, 60, PomodoroDefaults::SHORT_BREAK_MINUTES);
        $sessionsBeforeLongBreak = $this->clamp(
            $settings['sessionsBeforeLongBreak'],
            1,
            12,
            PomodoroDefaults::SESSIONS_BEFORE_LONG_BREAK,
        );
        $longBreakMinutes = $this->clamp($settings['longBreakMinutes'], 1, 60, PomodoroDefaults::LONG_BREAK_MINUTES);

        $withSettings = new PomodoroState(
            userId: $existing->userId,
            focusMinutes: $focusMinutes,
            shortBreakMinutes: $shortBreakMinutes,
            sessionsBeforeLongBreak: $sessionsBeforeLongBreak,
            longBreakMinutes: $longBreakMinutes,
            phase: $existing->phase,
            remainingMs: $existing->remainingMs,
            endsAt: $existing->endsAt,
            focusCount: $existing->focusCount,
            activeTodoId: $existing->activeTodoId,
            isRunning: $existing->isRunning,
            clientUpdatedAt: $existing->clientUpdatedAt,
            sessionUuid: $existing->sessionUuid,
            lastFocusedAt: $existing->lastFocusedAt,
        );

        $currentRemaining = $withSettings->isRunning && $withSettings->endsAt !== null
            ? max(0, $withSettings->endsAt - $nowMs)
            : max(0, $withSettings->remainingMs);
        $maxMs = $this->advancePomodoroPhase->durationMs($withSettings->phase, $withSettings);
        $nextRemaining = min($currentRemaining, $maxMs);

        $previousUuid = $withSettings->sessionUuid;

        if ($withSettings->isRunning) {
            if ($previousUuid !== null) {
                $this->cancelPomodoroPhaseJobs->execute($previousUuid);
            }
            $sessionUuid = (string) Str::uuid();
            $endsAt = $nowMs + $nextRemaining;
            $next = new PomodoroState(
                userId: $withSettings->userId,
                focusMinutes: $withSettings->focusMinutes,
                shortBreakMinutes: $withSettings->shortBreakMinutes,
                sessionsBeforeLongBreak: $withSettings->sessionsBeforeLongBreak,
                longBreakMinutes: $withSettings->longBreakMinutes,
                phase: $withSettings->phase,
                remainingMs: $nextRemaining,
                endsAt: $endsAt,
                focusCount: $withSettings->focusCount,
                activeTodoId: $withSettings->activeTodoId,
                isRunning: true,
                clientUpdatedAt: $nowMs,
                sessionUuid: $sessionUuid,
                lastFocusedAt: $withSettings->lastFocusedAt,
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

        $next = new PomodoroState(
            userId: $withSettings->userId,
            focusMinutes: $withSettings->focusMinutes,
            shortBreakMinutes: $withSettings->shortBreakMinutes,
            sessionsBeforeLongBreak: $withSettings->sessionsBeforeLongBreak,
            longBreakMinutes: $withSettings->longBreakMinutes,
            phase: $withSettings->phase,
            remainingMs: $nextRemaining,
            endsAt: null,
            focusCount: $withSettings->focusCount,
            activeTodoId: $withSettings->activeTodoId,
            isRunning: false,
            clientUpdatedAt: $nowMs,
            sessionUuid: null,
            lastFocusedAt: $withSettings->lastFocusedAt,
        );
        $saved = $this->repository->save($next);
        PomodoroStreamVersion::bump($userId);

        return $saved->toPayload(PomodoroStreamVersion::current($userId));
    }

    private function clamp(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return min($max, max($min, (int) round((float) $value)));
    }
}
