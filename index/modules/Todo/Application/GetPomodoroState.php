<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class GetPomodoroState
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly ReconcileOverduePomodoro $reconcileOverduePomodoro,
    ) {}

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
    public function execute(int $userId): array
    {
        $this->reconcileOverduePomodoro->execute($userId);

        $state = $this->repository->findByUserId($userId) ?? PomodoroState::defaultFor($userId);

        return $state->toPayload(PomodoroStreamVersion::current($userId));
    }
}
