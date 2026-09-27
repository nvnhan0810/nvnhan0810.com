<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Blog\Domain\Enums\SpecialTag;

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

        if (SpecialTag::isProtectedSlug($tag->slug)) {
            abort(403, 'This tag is managed in code and cannot be edited.');
        }

        return Inertia::render('private/tags/EditPage', [
            'initialTag' => $tag,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $tag = Tag::findOrFail($id);

        if (SpecialTag::isProtectedSlug($tag->slug)) {
            abort(403, 'This tag is managed in code and cannot be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tags,slug,'.$tag->id,
        ]);

        if (SpecialTag::isProtectedSlug($validated['slug'])) {
            abort(403, 'Cannot rename a tag to a protected slug.');
        }

        $tag->update($validated);

        return redirect()->route('admin.tags.index');
    }

    public function destroy(int $id)
    {
        $tag = Tag::findOrFail($id);

        if (SpecialTag::isProtectedSlug($tag->slug)) {
            abort(403, 'This tag is managed in code and cannot be deleted.');
        }

        $tag->delete();

        return redirect()->route('admin.tags.index');
    }
}
