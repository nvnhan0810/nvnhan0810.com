<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use App\Models\TodoProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = TodoProject::query()
            ->withCount('todos')
            ->orderBy('name')
            ->get();

        return Inertia::render('domains/todo/pages/admin/projects/ListPage', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('domains/todo/pages/admin/projects/FormPage', [
            'project' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        TodoProject::query()->create($data);

        return redirect()->route('todos.projects.index');
    }

    public function edit(string $id): Response
    {
        $project = TodoProject::query()->findOrFail($id);

        return Inertia::render('domains/todo/pages/admin/projects/FormPage', [
            'project' => $project,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $project = TodoProject::query()->findOrFail($id);
        $project->update($this->validated($request, $project->id));

        return redirect()->route('todos.projects.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        TodoProject::query()->findOrFail($id)->delete();

        return redirect()->route('todos.projects.index');
    }

    /**
     * @return array{name: string, git_repo_url: ?string, domain: ?string}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'git_repo_url' => $request->filled('git_repo_url') ? $request->string('git_repo_url')->toString() : null,
            'domain' => $request->filled('domain') ? $request->string('domain')->toString() : null,
        ]);

        $uniqueRepo = Rule::unique('todo_projects', 'git_repo_url');
        if ($ignoreId !== null) {
            $uniqueRepo = $uniqueRepo->ignore($ignoreId);
        }

        /** @var array{name: string, git_repo_url: ?string, domain: ?string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'git_repo_url' => ['nullable', 'string', 'max:2048', $uniqueRepo],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        return $data;
    }
}
