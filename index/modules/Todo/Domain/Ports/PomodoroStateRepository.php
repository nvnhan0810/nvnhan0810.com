<?php

namespace Modules\Todo\Domain\Ports;

use Modules\Todo\Domain\PomodoroState;

interface PomodoroStateRepository
{
    public function findByUserId(int $userId): ?PomodoroState;

    public function save(PomodoroState $state): PomodoroState;
}
