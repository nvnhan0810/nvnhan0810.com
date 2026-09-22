<?php

namespace Modules\Todo\Domain;

final class PomodoroState
{
    public function __construct(
        public readonly int $userId,
        public readonly int $focusMinutes,
        public readonly int $shortBreakMinutes,
        public readonly int $sessionsBeforeLongBreak,
        public readonly int $longBreakMinutes,
        public readonly string $phase,
        public readonly int $remainingMs,
        public readonly ?int $endsAt,
        public readonly int $focusCount,
        public readonly ?int $activeTodoId,
        public readonly bool $isRunning,
        public readonly int $clientUpdatedAt,
    ) {}

    public static function defaultFor(int $userId): self
    {
        return new self(
            userId: $userId,
            focusMinutes: PomodoroDefaults::FOCUS_MINUTES,
            shortBreakMinutes: PomodoroDefaults::SHORT_BREAK_MINUTES,
            sessionsBeforeLongBreak: PomodoroDefaults::SESSIONS_BEFORE_LONG_BREAK,
            longBreakMinutes: PomodoroDefaults::LONG_BREAK_MINUTES,
            phase: PomodoroDefaults::PHASE_FOCUS,
            remainingMs: PomodoroDefaults::remainingMsForFocus(),
            endsAt: null,
            focusCount: 0,
            activeTodoId: null,
            isRunning: false,
            clientUpdatedAt: 0,
        );
    }

    /**
     * @return array{
     *   settings: array{
     *     focusMinutes: int,
     *     shortBreakMinutes: int,
     *     sessionsBeforeLongBreak: int,
     *     longBreakMinutes: int
     *   },
     *   runtime: array{
     *     phase: string,
     *     remainingMs: int,
     *     endsAt: int|null,
     *     focusCount: int,
     *     activeTodoId: int|null,
     *     isRunning: bool,
     *     updatedAt: int
     *   },
     *   version: int
     * }
     */
    public function toPayload(int $version = 0): array
    {
        return [
            'settings' => [
                'focusMinutes' => $this->focusMinutes,
                'shortBreakMinutes' => $this->shortBreakMinutes,
                'sessionsBeforeLongBreak' => $this->sessionsBeforeLongBreak,
                'longBreakMinutes' => $this->longBreakMinutes,
            ],
            'runtime' => [
                'phase' => $this->phase,
                'remainingMs' => $this->remainingMs,
                'endsAt' => $this->endsAt,
                'focusCount' => $this->focusCount,
                'activeTodoId' => $this->activeTodoId,
                'isRunning' => $this->isRunning,
                'updatedAt' => $this->clientUpdatedAt,
            ],
            'version' => $version,
        ];
    }
}
