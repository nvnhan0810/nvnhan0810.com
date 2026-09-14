<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Helpers\SlugHelpers;
use App\Http\Controllers\Controller;
use App\Models\RdSource;
use App\Models\RdSubject;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = RdSubject::query()
            ->withCount('sources')
            ->orderBy('name')
            ->get();

        return Inertia::render('domains/reading-digest/pages/admin/subjects/ListPage', [
            'subjects' => $subjects,
        ]);
    }

    public function create()
    {
        $sources = RdSource::query()->orderBy('name')->get(['id', 'name', 'type']);

        return Inertia::render('domains/reading-digest/pages/admin/subjects/FormPage', [
            'subject' => null,
            'sources' => $sources,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'articles_per_digest' => 'integer|min:1|max:20',
            'enabled' => 'boolean',
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'uuid|exists:rd_sources,id',
        ]);

        $subject = RdSubject::create([
            'name' => $data['name'],
            'slug' => SlugHelpers::createFromString($data['name']),
            'description' => $data['description'] ?? null,
            'articles_per_digest' => $data['articles_per_digest'] ?? 5,
            'max_age_days' => 7,
            'enabled' => $data['enabled'] ?? true,
        ]);

        $subject->sources()->sync($data['source_ids'] ?? []);

        return redirect()->route('admin.reading-digest.subjects.index');
    }

    public function edit(string $id)
    {
        $subject = RdSubject::query()->with('sources')->findOrFail($id);
        $sources = RdSource::query()->orderBy('name')->get(['id', 'name', 'type']);

        return Inertia::render('domains/reading-digest/pages/admin/subjects/FormPage', [
            'subject' => $subject,
            'sources' => $sources,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $subject = RdSubject::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'articles_per_digest' => 'integer|min:1|max:20',
            'enabled' => 'boolean',
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'uuid|exists:rd_sources,id',
        ]);

        $subject->update([
            'name' => $data['name'],
            'slug' => SlugHelpers::createFromString($data['name']),
            'description' => $data['description'] ?? null,
            'articles_per_digest' => $data['articles_per_digest'] ?? 5,
            'enabled' => $data['enabled'] ?? true,
        ]);

        $subject->sources()->sync($data['source_ids'] ?? []);

        return redirect()->route('admin.reading-digest.subjects.index');
    }

    public function destroy(string $id)
    {
        RdSubject::query()->findOrFail($id)->delete();

        return redirect()->route('admin.reading-digest.subjects.index');
    }
}
