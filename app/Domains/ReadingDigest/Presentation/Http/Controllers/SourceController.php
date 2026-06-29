<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Application\Handlers\FetchSourceHandler;
use App\Domains\ReadingDigest\Domain\Enums\SourceType;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceTagMappingModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\TaxonomyNodeModel;
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
        $taxonomyNodes = TaxonomyNodeModel::query()->orderBy('path')->get(['id', 'label', 'path']);

        return Inertia::render('domains/reading-digest/pages/admin/sources/FormPage', [
            'source' => null,
            'sourceTypes' => collect(SourceType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'taxonomyNodes' => $taxonomyNodes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'url' => 'required|string|max:2048',
            'fetch_interval_minutes' => 'integer|min:15|max:1440',
            'enabled' => 'boolean',
            'config' => 'nullable|array',
            'tag_mappings' => 'nullable|array',
        ]);

        $source = SourceModel::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'url' => $data['url'],
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? 60,
            'enabled' => $data['enabled'] ?? true,
            'config' => $data['config'] ?? null,
        ]);

        $this->syncTagMappings($source, $data['tag_mappings'] ?? []);

        return redirect()->route('admin.reading-digest.sources.index');
    }

    public function edit(string $id)
    {
        $source = SourceModel::query()->with('tagMappings.taxonomyNode')->findOrFail($id);
        $taxonomyNodes = TaxonomyNodeModel::query()->orderBy('path')->get(['id', 'label', 'path']);

        return Inertia::render('domains/reading-digest/pages/admin/sources/FormPage', [
            'source' => $source,
            'sourceTypes' => collect(SourceType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'taxonomyNodes' => $taxonomyNodes,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $source = SourceModel::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'url' => 'required|string|max:2048',
            'fetch_interval_minutes' => 'integer|min:15|max:1440',
            'enabled' => 'boolean',
            'config' => 'nullable|array',
            'tag_mappings' => 'nullable|array',
        ]);

        $source->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'url' => $data['url'],
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? 60,
            'enabled' => $data['enabled'] ?? true,
            'config' => $data['config'] ?? null,
        ]);

        $this->syncTagMappings($source, $data['tag_mappings'] ?? []);

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
                'published_at' => $item->publishedAt?->format('c'),
            ]),
        ]);
    }

    public function fetchNow(string $id)
    {
        FetchSourceJob::dispatch($id);

        return back()->with('success', 'Fetch queued.');
    }

    private function syncTagMappings(SourceModel $source, array $mappings): void
    {
        SourceTagMappingModel::query()->where('source_id', $source->id)->delete();

        foreach ($mappings as $mapping) {
            if (empty($mapping['raw_tag']) || empty($mapping['taxonomy_node_id'])) {
                continue;
            }

            SourceTagMappingModel::create([
                'source_id' => $source->id,
                'raw_tag' => strtolower(trim($mapping['raw_tag'])),
                'taxonomy_node_id' => $mapping['taxonomy_node_id'],
            ]);
        }
    }
}
