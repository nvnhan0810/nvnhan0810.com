<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Http\Controllers\Controller;
use App\Models\RdTaxonomyNode;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxonomyController extends Controller
{
    public function index()
    {
        $nodes = RdTaxonomyNode::query()->orderBy('path')->get();

        return Inertia::render('domains/reading-digest/pages/admin/taxonomy/TaxonomyPage', [
            'nodes' => $nodes,
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
}
