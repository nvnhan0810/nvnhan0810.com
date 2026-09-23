<?php

namespace Modules\Todo\Domain;

use DateTimeImmutable;

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
        public readonly ?string $sessionUuid = null,
        public readonly ?DateTimeImmutable $lastFocusedAt = null,
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
            sessionUuid: null,
            lastFocusedAt: null,
        );
    }

    public function withUpdatedAt(?int $updatedAtMs = null): self
    {
        return new self(
            userId: $this->userId,
            focusMinutes: $this->focusMinutes,
            shortBreakMinutes: $this->shortBreakMinutes,
            sessionsBeforeLongBreak: $this->sessionsBeforeLongBreak,
            longBreakMinutes: $this->longBreakMinutes,
            phase: $this->phase,
            remainingMs: $this->remainingMs,
            endsAt: $this->endsAt,
            focusCount: $this->focusCount,
            activeTodoId: $this->activeTodoId,
            isRunning: $this->isRunning,
            clientUpdatedAt: $updatedAtMs ?? self::nowMs(),
            sessionUuid: $this->sessionUuid,
            lastFocusedAt: $this->lastFocusedAt,
        );
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
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
     *     updatedAt: int,
     *     sessionUuid: string|null
     *   },
     *   version: int
     * }
     */
    public function toPayload(int $version = 0): array
    {
        // endsAt = absolute ms when the phase job fires (source of truth while running).
        // remainingMs = frozen duration while paused only — FE must NOT mix with server now.
        // While running, FE displays max(0, endsAt - Date.now()) on the client clock.
        return [
            'settings' => [
                'focusMinutes' => $this->focusMinutes,
                'shortBreakMinutes' => $this->shortBreakMinutes,
                'sessionsBeforeLongBreak' => $this->sessionsBeforeLongBreak,
                'longBreakMinutes' => $this->longBreakMinutes,
            ],
            'runtime' => [
                'phase' => $this->phase,
                'remainingMs' => max(0, $this->remainingMs),
                'endsAt' => $this->endsAt,
                'focusCount' => $this->focusCount,
                'activeTodoId' => $this->activeTodoId,
                'isRunning' => $this->isRunning,
                'updatedAt' => $this->clientUpdatedAt,
                'sessionUuid' => $this->sessionUuid,
            ],
            'version' => $version,
        ];
    }
}
