<?php

namespace Modules\Todo\Infrastructure;

use App\Models\TodoPomodoroState as TodoPomodoroStateModel;
use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class EloquentPomodoroStateRepository implements PomodoroStateRepository
{
    public function findByUserId(int $userId): ?PomodoroState
    {
        $row = TodoPomodoroStateModel::query()->where('user_id', $userId)->first();
        if ($row === null) {
            return null;
        }

        return $this->toDomain($row);
    }

    public function save(PomodoroState $state): PomodoroState
    {
        $row = TodoPomodoroStateModel::query()->updateOrCreate(
            ['user_id' => $state->userId],
            [
                'focus_minutes' => $state->focusMinutes,
                'short_break_minutes' => $state->shortBreakMinutes,
                'sessions_before_long_break' => $state->sessionsBeforeLongBreak,
                'long_break_minutes' => $state->longBreakMinutes,
                'phase' => $state->phase,
                'remaining_ms' => $state->remainingMs,
                'ends_at' => $state->endsAt,
                'focus_count' => $state->focusCount,
                'active_todo_id' => $state->activeTodoId,
                'is_running' => $state->isRunning,
                'client_updated_at' => $state->clientUpdatedAt,
            ],
        );

        return $this->toDomain($row);
    }

    private function toDomain(TodoPomodoroStateModel $row): PomodoroState
    {
        $phase = in_array($row->phase, PomodoroDefaults::PHASES, true)
            ? $row->phase
            : PomodoroDefaults::PHASE_FOCUS;

        return new PomodoroState(
            userId: (int) $row->user_id,
            focusMinutes: (int) $row->focus_minutes,
            shortBreakMinutes: (int) $row->short_break_minutes,
            sessionsBeforeLongBreak: (int) $row->sessions_before_long_break,
            longBreakMinutes: (int) $row->long_break_minutes,
            phase: $phase,
            remainingMs: (int) $row->remaining_ms,
            endsAt: $row->ends_at !== null ? (int) $row->ends_at : null,
            focusCount: (int) $row->focus_count,
            activeTodoId: $row->active_todo_id !== null ? (int) $row->active_todo_id : null,
            isRunning: (bool) $row->is_running,
            clientUpdatedAt: (int) $row->client_updated_at,
        );
    }
}
