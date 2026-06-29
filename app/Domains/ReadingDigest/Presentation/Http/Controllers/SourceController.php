<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Application\Handlers\FetchSourceHandler;
use App\Domains\ReadingDigest\Domain\Enums\SourceType;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Domains\ReadingDigest\Presentation\Jobs\FetchSourceJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SourceController extends Controller
{
    public function index()
    {
        $sources = SourceModel::query()->orderBy('name')->get();

        return Inertia::render('domains/reading-digest/pages/admin/sources/ListPage', [
            'sources' => $sources,
            'sourceTypes' => collect(SourceType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('domains/reading-digest/pages/admin/sources/FormPage', $this->formProps(null));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'url' => 'required|string|max:2048',
            'enabled' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $source = SourceModel::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'url' => $data['url'],
            'fetch_interval_minutes' => 1440,
            'enabled' => $data['enabled'] ?? true,
            'config' => $data['config'] ?? null,
        ]);

        return redirect()->route('admin.reading-digest.sources.index');
    }

    public function edit(string $id)
    {
        $source = SourceModel::query()->with('tagMappings.taxonomyNode')->findOrFail($id);

        return Inertia::render('domains/reading-digest/pages/admin/sources/FormPage', $this->formProps($source));
    }

    public function update(Request $request, string $id)
    {
        $source = SourceModel::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'url' => 'required|string|max:2048',
            'enabled' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $source->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'url' => $data['url'],
            'enabled' => $data['enabled'] ?? true,
            'config' => $data['config'] ?? null,
        ]);

        return redirect()->route('admin.reading-digest.sources.index');
    }

    public function destroy(string $id)
    {
        SourceModel::query()->findOrFail($id)->delete();

        return redirect()->route('admin.reading-digest.sources.index');
    }

    public function testFetch(string $id, FetchSourceHandler $handler)
    {
        $source = SourceModel::query()->findOrFail($id);
        $fetcher = app(\App\Domains\ReadingDigest\Infrastructure\Sources\SourceFetcherRegistry::class)->for($source);
        $preview = $fetcher->fetch($source, 5);

        return response()->json([
            'items' => collect($preview)->map(fn ($item) => [
                'title' => $item->title,
                'url' => $item->url,
                'summary' => $item->summary,
                'published_at' => $item->publishedAt?->format('c'),
            ]),
        ]);
    }

    public function fetchNow(string $id)
    {
        FetchSourceJob::dispatch($id);

        return back()->with('success', 'Fetch queued.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?SourceModel $source): array
    {
        return [
            'source' => $source,
            'sourceTypes' => collect(SourceType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'description' => $t->description(),
            ]),
        ];
    }
}
