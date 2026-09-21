<?php

namespace Modules\Todo\Application;

use App\Models\Todo;
use Illuminate\Support\Collection;

final class BuildMatrixQuadrants
{
    /** @var list<string> */
    public const ACTIVE_STATUSES = ['todo', 'in_progress'];

    /**
     * @return array{
     *   do: list<Todo>,
     *   schedule: list<Todo>,
     *   delegate: list<Todo>,
     *   eliminate: list<Todo>
     * }
     */
    public function execute(): array
    {
        /** @var Collection<int, Todo> $todos */
        $todos = Todo::query()
            ->with('project:id,name')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->get();

        return [
            'do' => $todos->filter(fn (Todo $t) => $t->is_urgent && $t->is_important)->values()->all(),
            'schedule' => $todos->filter(fn (Todo $t) => ! $t->is_urgent && $t->is_important)->values()->all(),
            'delegate' => $todos->filter(fn (Todo $t) => $t->is_urgent && ! $t->is_important)->values()->all(),
            'eliminate' => $todos->filter(fn (Todo $t) => ! $t->is_urgent && ! $t->is_important)->values()->all(),
        ];
    }
}
