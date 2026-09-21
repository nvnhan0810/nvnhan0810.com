<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use App\Models\EisenhowerLog;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MatrixController extends Controller
{
    /** @var list<string> */
    private const ACTIVE_STATUSES = ['todo', 'in_progress'];

    public function index(): Response
    {
        $todos = Todo::query()
            ->with('project:id,name')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->get();

        $quadrants = [
            'do' => $todos->filter(fn (Todo $t) => $t->is_urgent && $t->is_important)->values(),
            'schedule' => $todos->filter(fn (Todo $t) => ! $t->is_urgent && $t->is_important)->values(),
            'delegate' => $todos->filter(fn (Todo $t) => $t->is_urgent && ! $t->is_important)->values(),
            'eliminate' => $todos->filter(fn (Todo $t) => ! $t->is_urgent && ! $t->is_important)->values(),
        ];

        return Inertia::render('domains/todo/pages/matrix/MatrixPage', [
            'quadrants' => $quadrants,
            'statuses' => self::ACTIVE_STATUSES,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $todo = Todo::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->findOrFail($id);

        $data = $request->validate([
            'is_urgent' => ['required', 'boolean'],
            'is_important' => ['required', 'boolean'],
            'status' => ['sometimes', Rule::in(self::ACTIVE_STATUSES)],
        ]);

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
}
