<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use App\Models\EisenhowerLog;
use App\Models\Todo;
use App\Models\TodoProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Todo\Application\BuildMatrixQuadrants;
use Modules\Todo\Application\GetPomodoroState;
use Modules\Todo\Application\PausePomodoro;
use Modules\Todo\Application\PromoteBacklogItems;
use Modules\Todo\Application\ResetPomodoro;
use Modules\Todo\Application\SelectPomodoroActiveTodo;
use Modules\Todo\Application\SkipPomodoroPhase;
use Modules\Todo\Application\StartPomodoro;
use Modules\Todo\Application\TouchPomodoroFocus;
use Modules\Todo\Application\UpdatePomodoroSettings;
use Modules\Todo\Domain\MatrixStreamVersion;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatrixController extends Controller
{
    public function __construct(
        private readonly BuildMatrixQuadrants $buildMatrixQuadrants,
        private readonly PromoteBacklogItems $promoteBacklogItems,
        private readonly GetPomodoroState $getPomodoroState,
    ) {}

    public function index(): Response
    {
        $userId = (int) Auth::id();

        return Inertia::render('domains/todo/pages/matrix/MatrixPage', [
            'quadrants' => $this->buildMatrixQuadrants->execute(),
            'stream_url' => route('matrix.stream'),
            'version' => MatrixStreamVersion::current(),
            'pomodoro' => $this->getPomodoroState->execute($userId),
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

        $userId = (int) Auth::id();

        return response()->stream(function () use ($userId): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $lastMatrixVersion = -1;
            $lastPomodoroVersion = -1;
            $startedAt = time();
            $maxSeconds = 120;

            while (! connection_aborted() && (time() - $startedAt) < $maxSeconds) {
                $matrixVersion = MatrixStreamVersion::current();
                $pomodoroVersion = PomodoroStreamVersion::current($userId);
                $sentEvent = false;

                if ($matrixVersion !== $lastMatrixVersion) {
                    $lastMatrixVersion = $matrixVersion;
                    $payload = json_encode([
                        'version' => $matrixVersion,
                        'quadrants' => $this->buildMatrixQuadrants->execute(),
                    ], JSON_THROW_ON_ERROR);

                    echo "event: matrix\n";
                    echo 'data: '.$payload."\n\n";
                    $sentEvent = true;
                }

                if ($pomodoroVersion !== $lastPomodoroVersion) {
                    $lastPomodoroVersion = $pomodoroVersion;
                    $pomodoroPayload = json_encode(
                        $this->getPomodoroState->execute($userId),
                        JSON_THROW_ON_ERROR,
                    );

                    echo "event: pomodoro\n";
                    echo 'data: '.$pomodoroPayload."\n\n";
                    $sentEvent = true;
                }

                if (! $sentEvent) {
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

    public function showPomodoro(): JsonResponse
    {
        $userId = (int) Auth::id();

        return response()->json($this->getPomodoroState->execute($userId));
    }

    public function startPomodoro(Request $request, StartPomodoro $start): JsonResponse
    {
        $data = $request->validate([
            'activeTodoId' => ['sometimes', 'nullable', 'integer', 'exists:todos,id'],
        ]);

        $activeTodoId = array_key_exists('activeTodoId', $data) && is_numeric($data['activeTodoId'] ?? null)
            ? (int) $data['activeTodoId']
            : null;

        return response()->json($start->execute((int) Auth::id(), $activeTodoId));
    }

    public function pausePomodoro(PausePomodoro $pause): JsonResponse
    {
        return response()->json($pause->execute((int) Auth::id()));
    }

    public function skipPomodoro(SkipPomodoroPhase $skip): JsonResponse
    {
        return response()->json($skip->execute((int) Auth::id()));
    }

    public function resetPomodoro(ResetPomodoro $reset): JsonResponse
    {
        return response()->json($reset->execute((int) Auth::id()));
    }

    public function updatePomodoroSettings(Request $request, UpdatePomodoroSettings $update): JsonResponse
    {
        $data = $request->validate([
            'focusMinutes' => ['required', 'integer', 'min:1', 'max:180'],
            'shortBreakMinutes' => ['required', 'integer', 'min:1', 'max:60'],
            'sessionsBeforeLongBreak' => ['required', 'integer', 'min:1', 'max:12'],
            'longBreakMinutes' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        return response()->json($update->execute((int) Auth::id(), $data));
    }

    public function updatePomodoroActiveTodo(Request $request, SelectPomodoroActiveTodo $select): JsonResponse
    {
        $data = $request->validate([
            'activeTodoId' => ['nullable', 'integer', 'exists:todos,id'],
        ]);

        $activeTodoId = isset($data['activeTodoId']) && is_numeric($data['activeTodoId'])
            ? (int) $data['activeTodoId']
            : null;

        return response()->json($select->execute((int) Auth::id(), $activeTodoId));
    }

    public function focusPomodoro(Request $request, TouchPomodoroFocus $touch): JsonResponse
    {
        $data = $request->validate([
            'sessionUuid' => ['required', 'uuid'],
            'focused' => ['required', 'boolean'],
        ]);

        $touch->execute((int) Auth::id(), (string) $data['sessionUuid'], $request->boolean('focused'));

        return response()->json(['ok' => true]);
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
