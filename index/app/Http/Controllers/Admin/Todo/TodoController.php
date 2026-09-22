<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use App\Models\TodoProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TodoController extends Controller
{
    public function index(Request $request): Response
    {
        $projectId = $request->query('project_id');

        $todos = Todo::query()
            ->with('project:id,name')
            ->when(
                is_numeric($projectId),
                fn ($q) => $q->where('project_id', (int) $projectId),
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
            )
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('domains/todo/pages/admin/todos/ListPage', [
            'todos' => $todos,
            'projects' => TodoProject::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => is_numeric($projectId) ? (int) $projectId : null,
                'status' => $request->query('status'),
            ],
            'statuses' => Todo::STATUSES,
            'priorities' => Todo::PRIORITIES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('domains/todo/pages/admin/todos/FormPage', $this->formProps(
            null,
            is_numeric($request->query('project_id')) ? (int) $request->query('project_id') : null,
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Todo::query()->create($this->validated($request));

        return $this->redirectAfterSave($request);
    }

    public function edit(string $id): Response
    {
        $todo = Todo::query()->findOrFail($id);

        return Inertia::render('domains/todo/pages/admin/todos/FormPage', $this->formProps($todo));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $todo = Todo::query()->findOrFail($id);
        $data = $this->validated($request);

        if ($data['status'] === 'done' && $todo->closed_at === null) {
            $data['closed_at'] = now();
        }
        if ($data['status'] === 'in_progress' && $todo->started_at === null) {
            $data['started_at'] = now();
        }
        if (! in_array($data['status'], ['done', 'rejected'], true)) {
            $data['closed_at'] = null;
        }

        $todo->update($data);

        return $this->redirectAfterSave($request);
    }

    public function destroy(string $id): RedirectResponse
    {
        Todo::query()->findOrFail($id)->delete();

        return redirect()->route('todos.index');
    }

    private function redirectAfterSave(Request $request): RedirectResponse
    {
        if ($request->string('return_to')->toString() === 'matrix') {
            return redirect()->route('matrix.index');
        }

        return redirect()->route('todos.index');
    }

    /**
     * @return array{todo: ?Todo, projects: \Illuminate\Support\Collection<int, TodoProject>, statuses: list<string>, priorities: list<string>}
     */
    private function formProps(?Todo $todo, ?int $defaultProjectId = null): array
    {
        return [
            'todo' => $todo,
            'projects' => TodoProject::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Todo::STATUSES,
            'priorities' => Todo::PRIORITIES,
            'default_project_id' => $defaultProjectId,
        ];
    }

    /**
     * @return array{
     *   project_id: ?int,
     *   title: string,
     *   description: ?string,
     *   status: string,
     *   priority: string,
     *   due_at: ?string,
     *   is_urgent: bool,
     *   is_important: bool
     * }
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'project_id' => $request->filled('project_id') ? $request->integer('project_id') : null,
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
            'due_at' => $request->filled('due_at') ? $request->string('due_at')->toString() : null,
            'is_urgent' => $request->boolean('is_urgent'),
            'is_important' => $request->boolean('is_important'),
        ]);

        /** @var array{
         *   project_id: ?int,
         *   title: string,
         *   description: ?string,
         *   status: string,
         *   priority: string,
         *   due_at: ?string,
         *   is_urgent: bool,
         *   is_important: bool
         * } $data
         */
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:todo_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Todo::STATUSES)],
            'priority' => ['required', Rule::in(Todo::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'is_urgent' => ['boolean'],
            'is_important' => ['boolean'],
        ]);

        return $data;
    }
}
