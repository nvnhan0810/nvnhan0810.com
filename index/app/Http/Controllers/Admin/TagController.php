<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('posts')->paginate(50);

        return Inertia::render('private/tags/ListPage', [
            'tags' => $tags,
        ]);
    }

    public function edit(int $id)
    {
        $tag = Tag::findOrFail($id);

        return Inertia::render('private/tags/EditPage', [
            'initialTag' => $tag,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $tag = Tag::findOrFail($id);

        $tag->update($request->all());

        return redirect()->route('admin.tags.index');
    }

    public function destroy(int $id)
    {
        $tag = Tag::findOrFail($id);

        $tag->delete();

        return redirect()->route('admin.tags.index');
    }
}
