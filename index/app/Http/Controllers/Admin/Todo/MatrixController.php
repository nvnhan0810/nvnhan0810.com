<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use App\Models\EisenhowerLog;
use App\Models\Todo;
use App\Models\TodoProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Todo\Application\BuildMatrixQuadrants;
use Modules\Todo\Application\PromoteBacklogItems;
use Modules\Todo\Domain\MatrixStreamVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatrixController extends Controller
{
    public function __construct(
        private readonly BuildMatrixQuadrants $buildMatrixQuadrants,
        private readonly PromoteBacklogItems $promoteBacklogItems,
    ) {}

    public function index(): Response
    {
        return Inertia::render('domains/todo/pages/matrix/MatrixPage', [
            'quadrants' => $this->buildMatrixQuadrants->execute(),
            'stream_url' => route('matrix.stream'),
            'version' => MatrixStreamVersion::current(),
            'projects' => TodoProject::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Todo::STATUSES,
            'priorities' => Todo::PRIORITIES,
            'backlog' => Todo::query()
                ->with('project:id,name')
                ->where('status', PromoteBacklogItems::SOURCE_STATUS)
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_at')
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        // Release session lock so other requests are not blocked by the long-lived stream.
        if ($request->hasSession()) {
            $request->session()->save();
        }

        return response()->stream(function (): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $lastVersion = -1;
            $startedAt = time();
            $maxSeconds = 120;

            while (! connection_aborted() && (time() - $startedAt) < $maxSeconds) {
                $version = MatrixStreamVersion::current();

                if ($version !== $lastVersion) {
                    $lastVersion = $version;
                    $payload = json_encode([
                        'version' => $version,
                        'quadrants' => $this->buildMatrixQuadrants->execute(),
                    ], JSON_THROW_ON_ERROR);

                    echo "event: matrix\n";
                    echo 'data: '.$payload."\n\n";
                } else {
                    echo ": ping\n\n";
                }

                if (function_exists('flush')) {
                    flush();
                }

                usleep(1_000_000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $todo = Todo::query()
            ->whereIn('status', BuildMatrixQuadrants::ACTIVE_STATUSES)
            ->findOrFail($id);

        $data = $request->validate([
            'is_urgent' => ['required', 'boolean'],
            'is_important' => ['required', 'boolean'],
            'status' => ['sometimes', Rule::in(BuildMatrixQuadrants::ACTIVE_STATUSES)],
        ]);

        $data['is_important'] = $request->boolean('is_important');
        $data['is_urgent'] = $request->boolean('is_urgent');

        $todo->update([
            'is_urgent' => $data['is_urgent'],
            'is_important' => $data['is_important'],
            'status' => $data['status'] ?? $todo->status,
            'started_at' => ($data['status'] ?? $todo->status) === 'in_progress' && $todo->started_at === null
                ? now()
                : $todo->started_at,
        ]);

        $userId = Auth::id();
        if ($userId !== null) {
            EisenhowerLog::query()->create([
                'todo_id' => $todo->id,
                'is_urgent' => $todo->is_urgent,
                'is_important' => $todo->is_important,
                'created_by' => $userId,
            ]);
        }

        return redirect()->route('matrix.index');
    }

    public function promoteBacklog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:todos,id'],
            'items.*.is_urgent' => ['required', 'boolean'],
            'items.*.is_important' => ['required', 'boolean'],
        ]);

        /** @var list<array{id: int, is_urgent: bool, is_important: bool}> $items */
        $items = array_map(
            static fn (array $item): array => [
                'id' => (int) $item['id'],
                'is_urgent' => filter_var($item['is_urgent'], FILTER_VALIDATE_BOOLEAN),
                'is_important' => filter_var($item['is_important'], FILTER_VALIDATE_BOOLEAN),
            ],
            $data['items'],
        );

        $this->promoteBacklogItems->execute($items, Auth::id());

        return redirect()->route('matrix.index');
    }

    public function complete(string $id): RedirectResponse
    {
        $todo = Todo::query()
            ->whereIn('status', BuildMatrixQuadrants::ACTIVE_STATUSES)
            ->findOrFail($id);

        $todo->update([
            'status' => 'done',
            'closed_at' => now(),
        ]);

        return redirect()->route('matrix.index');
    }
}
