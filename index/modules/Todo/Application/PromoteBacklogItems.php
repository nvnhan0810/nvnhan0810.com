<?php

namespace Modules\Todo\Application;

use App\Models\EisenhowerLog;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;

final class PromoteBacklogItems
{
    public const SOURCE_STATUS = 'backlog';

    public const TARGET_STATUS = 'todo';

    /**
     * @param list<array{id: int, is_urgent: bool, is_important: bool}> $items
     */
    public function execute(array $items, ?int $actorUserId): int
    {
        if ($items === []) {
            return 0;
        }

        return (int) DB::transaction(function () use ($items, $actorUserId): int {
            $ids = array_values(array_unique(array_map(
                static fn (array $item): int => $item['id'],
                $items,
            )));

            /** @var \Illuminate\Support\Collection<int, Todo> $todos */
            $todos = Todo::query()
                ->where('status', self::SOURCE_STATUS)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $updated = 0;

            foreach ($items as $item) {
                $todo = $todos->get($item['id']);
                if ($todo === null) {
                    continue;
                }

                $todo->update([
                    'status' => self::TARGET_STATUS,
                    'is_urgent' => $item['is_urgent'],
                    'is_important' => $item['is_important'],
                ]);

                if ($actorUserId !== null) {
                    EisenhowerLog::query()->create([
                        'todo_id' => $todo->id,
                        'is_urgent' => $todo->is_urgent,
                        'is_important' => $todo->is_important,
                        'created_by' => $actorUserId,
                    ]);
                }

                $updated++;
            }

            return $updated;
        });
    }
}
