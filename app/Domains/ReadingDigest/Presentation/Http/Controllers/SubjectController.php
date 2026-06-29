<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SubjectModel;
use App\Helpers\SlugHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = SubjectModel::query()
            ->withCount('sources')
            ->orderBy('name')
            ->get();

        return Inertia::render('domains/reading-digest/pages/admin/subjects/ListPage', [
            'subjects' => $subjects,
        ]);
    }

    public function create()
    {
        $sources = SourceModel::query()->orderBy('name')->get(['id', 'name', 'type']);

        return Inertia::render('domains/reading-digest/pages/admin/subjects/FormPage', [
            'subject' => null,
            'sources' => $sources,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:rd_subjects,slug',
            'description' => 'nullable|string',
            'articles_per_digest' => 'integer|min:1|max:20',
            'max_age_days' => 'integer|min:1|max:90',
            'enabled' => 'boolean',
            'source_ids' => 'array',
            'source_ids.*' => 'uuid|exists:rd_sources,id',
        ]);

        $subject = SubjectModel::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? SlugHelpers::createFromString($data['name']),
            'description' => $data['description'] ?? null,
            'articles_per_digest' => $data['articles_per_digest'] ?? 5,
            'max_age_days' => $data['max_age_days'] ?? 7,
            'enabled' => $data['enabled'] ?? true,
        ]);

        $subject->sources()->sync($data['source_ids'] ?? []);

        return redirect()->route('admin.reading-digest.subjects.index');
    }

    public function edit(string $id)
    {
        $subject = SubjectModel::query()->with('sources')->findOrFail($id);
        $sources = SourceModel::query()->orderBy('name')->get(['id', 'name', 'type']);

        return Inertia::render('domains/reading-digest/pages/admin/subjects/FormPage', [
            'subject' => $subject,
            'sources' => $sources,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $subject = SubjectModel::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:rd_subjects,slug,'.$id,
            'description' => 'nullable|string',
            'articles_per_digest' => 'integer|min:1|max:20',
            'max_age_days' => 'integer|min:1|max:90',
            'enabled' => 'boolean',
            'source_ids' => 'array',
            'source_ids.*' => 'uuid|exists:rd_sources,id',
        ]);

        $subject->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'articles_per_digest' => $data['articles_per_digest'] ?? 5,
            'max_age_days' => $data['max_age_days'] ?? 7,
            'enabled' => $data['enabled'] ?? true,
        ]);

        $subject->sources()->sync($data['source_ids'] ?? []);

        return redirect()->route('admin.reading-digest.subjects.index');
    }

    public function destroy(string $id)
    {
        SubjectModel::query()->findOrFail($id)->delete();

        return redirect()->route('admin.reading-digest.subjects.index');
    }
}
