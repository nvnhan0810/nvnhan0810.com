<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;

final class AdvancePomodoroPhase
{
    public function advance(PomodoroState $state): PomodoroState
    {
        if ($state->phase === PomodoroDefaults::PHASE_FOCUS) {
            $completedCount = $state->focusCount + 1;
            $nextPhase = $completedCount >= $state->sessionsBeforeLongBreak
                ? PomodoroDefaults::PHASE_LONG_BREAK
                : PomodoroDefaults::PHASE_SHORT_BREAK;
            $focusCount = $nextPhase === PomodoroDefaults::PHASE_LONG_BREAK ? 0 : $completedCount;
            $durationMs = $this->durationMs($nextPhase, $state);
            $wasRunning = $state->isRunning;

            return new PomodoroState(
                userId: $state->userId,
                focusMinutes: $state->focusMinutes,
                shortBreakMinutes: $state->shortBreakMinutes,
                sessionsBeforeLongBreak: $state->sessionsBeforeLongBreak,
                longBreakMinutes: $state->longBreakMinutes,
                phase: $nextPhase,
                remainingMs: $durationMs,
                endsAt: $wasRunning ? ((int) floor(microtime(true) * 1000)) + $durationMs : null,
                focusCount: $focusCount,
                activeTodoId: $state->activeTodoId,
                isRunning: $wasRunning,
                clientUpdatedAt: max($state->clientUpdatedAt + 1, (int) floor(microtime(true) * 1000)),
            );
        }

        $durationMs = $this->durationMs(PomodoroDefaults::PHASE_FOCUS, $state);
        $wasRunning = $state->isRunning;

        return new PomodoroState(
            userId: $state->userId,
            focusMinutes: $state->focusMinutes,
            shortBreakMinutes: $state->shortBreakMinutes,
            sessionsBeforeLongBreak: $state->sessionsBeforeLongBreak,
            longBreakMinutes: $state->longBreakMinutes,
            phase: PomodoroDefaults::PHASE_FOCUS,
            remainingMs: $durationMs,
            endsAt: $wasRunning ? ((int) floor(microtime(true) * 1000)) + $durationMs : null,
            focusCount: $state->focusCount,
            activeTodoId: $state->activeTodoId,
            isRunning: $wasRunning,
            clientUpdatedAt: max($state->clientUpdatedAt + 1, (int) floor(microtime(true) * 1000)),
        );
    }

    private function durationMs(string $phase, PomodoroState $state): int
    {
        return match ($phase) {
            PomodoroDefaults::PHASE_FOCUS => $state->focusMinutes * 60_000,
            PomodoroDefaults::PHASE_SHORT_BREAK => $state->shortBreakMinutes * 60_000,
            PomodoroDefaults::PHASE_LONG_BREAK => $state->longBreakMinutes * 60_000,
            default => PomodoroDefaults::remainingMsForFocus(),
        };
    }
}
