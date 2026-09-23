<?php

namespace Modules\Todo\Application;

use DateTimeImmutable;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class TouchPomodoroFocus
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
    ) {}

    public function execute(int $userId, string $sessionUuid, bool $focused): void
    {
        if (! $focused || $sessionUuid === '') {
            return;
        }

        $existing = $this->repository->findByUserId($userId);
        if ($existing === null) {
            return;
        }

        if (
            ! $existing->isRunning
            || $existing->sessionUuid === null
            || $existing->sessionUuid !== $sessionUuid
        ) {
            return;
        }

        $next = new PomodoroState(
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
            isRunning: $existing->isRunning,
            clientUpdatedAt: $existing->clientUpdatedAt,
            sessionUuid: $existing->sessionUuid,
            lastFocusedAt: new DateTimeImmutable('now'),
        );

        $this->repository->save($next);
        // Do not bump SSE — focus heartbeat must stay quiet for clients.
    }
}
