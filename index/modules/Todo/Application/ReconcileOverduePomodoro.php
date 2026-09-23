<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

/**
 * If a running session is past endsAt (job delayed / missed), advance now.
 */
final class ReconcileOverduePomodoro
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly DeliverPomodoroPhasePush $deliverPomodoroPhasePush,
    ) {}

    public function execute(int $userId): void
    {
        $state = $this->repository->findByUserId($userId);
        if ($state === null) {
            return;
        }

        $guard = 0;
        while ($guard < 8) {
            $guard++;
            $state = $this->repository->findByUserId($userId);
            if (
                $state === null
                || ! $state->isRunning
                || $state->endsAt === null
                || $state->sessionUuid === null
            ) {
                return;
            }

            $nowMs = PomodoroState::nowMs();
            if ($state->endsAt > $nowMs + 1500) {
                return;
            }

            $this->deliverPomodoroPhasePush->execute(
                $userId,
                $state->sessionUuid,
                (int) $state->endsAt,
                $state->phase,
            );
        }

        // Touch stream version in case deliver no-op'd without bump (stale).
        PomodoroStreamVersion::current($userId);
    }
}
