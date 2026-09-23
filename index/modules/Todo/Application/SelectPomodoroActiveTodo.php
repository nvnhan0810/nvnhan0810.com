<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class SelectPomodoroActiveTodo
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
    ) {}

    /**
     * @return array{
     *   settings: array{focusMinutes: int, shortBreakMinutes: int, sessionsBeforeLongBreak: int, longBreakMinutes: int},
     *   runtime: array{phase: string, remainingMs: int, endsAt: int|null, focusCount: int, activeTodoId: int|null, isRunning: bool, updatedAt: int, sessionUuid: string|null},
     *   version: int
     * }
     */
    public function execute(int $userId, ?int $activeTodoId): array
    {
        $existing = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);
        $nowMs = PomodoroState::nowMs();

        $remainingMs = $existing->isRunning && $existing->endsAt !== null
            ? max(0, $existing->endsAt - $nowMs)
            : max(0, $existing->remainingMs);

        $next = new PomodoroState(
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
            isRunning: $existing->isRunning,
            clientUpdatedAt: $nowMs,
            sessionUuid: $existing->sessionUuid,
            lastFocusedAt: $existing->lastFocusedAt,
        );

        $saved = $this->repository->save($next);
        PomodoroStreamVersion::bump($userId);

        return $saved->toPayload(PomodoroStreamVersion::current($userId));
    }
}
