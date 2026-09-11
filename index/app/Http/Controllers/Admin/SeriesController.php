<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Post;
use Illuminate\Support\Str;
use App\Helpers\SlugHelpers;

class SeriesController extends Controller
{
    public function index()
    {
        $series = Series::paginate(50);

        $series->loadCount(['posts']);

        return Inertia::render('private/series/ListPage', [
            'series' => $series,
        ]);
    }

    public function create()
    {
        $posts = Post::orderBy('title', 'ASC')->get();

        return Inertia::render('private/series/CreatePage', [
            'posts' => $posts,
        ]);
    }

    public function store(Request $request)
    {
        $slug = SlugHelpers::createFromString($request->name);

        $series = Series::create([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => $slug,
        ]);

        $series->posts()->sync($request->posts);

        return redirect()->route('admin.series.index');
    }

    public function edit(int $id)
    {
        $series = Series::with(['posts'])->find($id);

        return Inertia::render('private/series/EditPage', [
            'series' => $series,
            'posts' => Post::orderBy('title', 'ASC')->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $series = Series::findOrFail($id);

        $series->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $series->posts()->detach();
        $series->posts()->sync($request->posts);

        return redirect()->route('admin.series.index');
    }

    public function destroy(int $id)
    {
        $series = Series::findOrFail($id);

        $series->posts()->detach();

        $series->delete();

        return redirect()->route('admin.series.index');
    }
}
