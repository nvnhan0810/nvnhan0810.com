<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Http\Controllers\Controller;
use App\Models\RdTaxonomyNode;
use App\Models\RdUserReadingProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;

class TaxonomyController extends Controller
{
    private const DEFAULT_PRIORITY_WEIGHT = 8.0;

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $nodes = RdTaxonomyNode::query()->orderBy('path')->get();

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();

        return Inertia::render('domains/reading-digest/pages/admin/taxonomy/TaxonomyPage', [
            'nodes' => $nodes,
            'favoriteTopics' => $preferences['favorite_topics'] ?? [],
            'ignoredTaxonomyIds' => $preferences['ignored_taxonomy_ids'] ?? [],
            'ignoredTopics' => $preferences['ignored_topics'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:rd_taxonomy_nodes,slug',
            'parent_id' => 'nullable|uuid|exists:rd_taxonomy_nodes,id',
        ]);

        $parentPath = $data['parent_id']
            ? RdTaxonomyNode::query()->find($data['parent_id'])?->path
            : null;

        $path = $parentPath ? $parentPath.'.'.$data['slug'] : $data['slug'];

        RdTaxonomyNode::create([
            'label' => $data['label'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'path' => $path,
        ]);

        return back();
    }

    public function addPriority(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'taxonomy_node_id' => 'required|uuid|exists:rd_taxonomy_nodes,id',
            'weight' => 'nullable|numeric|min:1|max:100',
        ]);

        $node = RdTaxonomyNode::query()->findOrFail($data['taxonomy_node_id']);
        $weight = (float) ($data['weight'] ?? self::DEFAULT_PRIORITY_WEIGHT);

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();
        $favorites = $preferences['favorite_topics'] ?? [];
        $favorites[$node->path] = $weight;
        $preferences['favorite_topics'] = $favorites;

        $ignoredIds = $preferences['ignored_taxonomy_ids'] ?? [];
        $preferences['ignored_taxonomy_ids'] = array_values(
            array_filter($ignoredIds, fn ($id) => $id !== $node->id)
        );

        $profile->update(['preferences' => $preferences]);

        return back()->with('success', 'Đã thêm tag ưu tiên.');
    }

    public function removePriority(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'path' => 'required|string|max:255',
        ]);

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();
        $favorites = $preferences['favorite_topics'] ?? [];
        unset($favorites[$data['path']]);
        $preferences['favorite_topics'] = $favorites;

        $profile->update(['preferences' => $preferences]);

        return back()->with('success', 'Đã bỏ tag ưu tiên.');
    }

    public function addRestricted(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'taxonomy_node_id' => 'required|uuid|exists:rd_taxonomy_nodes,id',
        ]);

        $node = RdTaxonomyNode::query()->findOrFail($data['taxonomy_node_id']);

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();

        $ignoredIds = $preferences['ignored_taxonomy_ids'] ?? [];
        if (! in_array($node->id, $ignoredIds, true)) {
            $ignoredIds[] = $node->id;
        }
        $preferences['ignored_taxonomy_ids'] = array_values($ignoredIds);

        $ignoredTopics = $preferences['ignored_topics'] ?? [];
        if (! in_array($node->path, $ignoredTopics, true)) {
            $ignoredTopics[] = $node->path;
        }
        $preferences['ignored_topics'] = array_values($ignoredTopics);

        $favorites = $preferences['favorite_topics'] ?? [];
        unset($favorites[$node->path]);
        $preferences['favorite_topics'] = $favorites;

        $profile->update(['preferences' => $preferences]);

        return back()->with('success', 'Đã thêm tag hạn chế — bài gắn tag này sẽ bị loại khi lấy digest.');
    }

    public function removeRestricted(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'taxonomy_node_id' => 'nullable|uuid|exists:rd_taxonomy_nodes,id',
            'path' => 'nullable|string|max:255',
        ]);

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();

        $nodeId = $data['taxonomy_node_id'] ?? null;
        $path = $data['path'] ?? null;

        if ($nodeId === null && $path !== null) {
            $nodeId = RdTaxonomyNode::query()->where('path', $path)->value('id');
        }

        if ($nodeId !== null) {
            $preferences['ignored_taxonomy_ids'] = array_values(
                array_filter(
                    $preferences['ignored_taxonomy_ids'] ?? [],
                    fn ($id) => $id !== $nodeId
                )
            );

            $nodePath = RdTaxonomyNode::query()->where('id', $nodeId)->value('path');
            if (is_string($nodePath)) {
                $path = $nodePath;
            }
        }

        if (is_string($path) && $path !== '') {
            $preferences['ignored_topics'] = array_values(
                array_filter(
                    $preferences['ignored_topics'] ?? [],
                    fn ($topic) => $topic !== $path
                )
            );
        }

        $profile->update(['preferences' => $preferences]);

        return back()->with('success', 'Đã bỏ tag hạn chế.');
    }
}
