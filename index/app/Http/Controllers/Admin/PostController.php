<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SlugHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        $posts = Post::with(['tags'])
            ->when($search, function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'DESC')
            ->paginate(50);

        return Inertia::render('private/posts/ListPage', [
            'posts' => $posts,
        ]);
    }

    public function create()
    {
        $series = Series::all();

        return Inertia::render('private/posts/CreatePage', [
            'series' => $series,
        ]);
    }

    public function store(CreatePostRequest $request)
    {
        $validated = $request->validated();
        $slug = $this->generateSlugForPost($validated['title']);

        try {
            DB::beginTransaction();

            $post = Post::create([
                'slug' => $slug,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'content' => $validated['content'],
                'source_url' => $validated['source_url'] ?? null,
                'is_published' => $request->boolean('is_published'),
                'published_at' => $request->published_at,
            ]);

            if ($request->tags) {
                $tagIds = $this->getTagsInfo($request->tags);
                $post->tags()->sync($tagIds);
            }

            $this->syncSeries($post, $request->series_ids);

            DB::commit();

            return redirect()->route('admin.index');
        } catch (Throwable $e) {
            DB::rollBack();

            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors('Create post failed');
        }
    }

    public function edit(int $id)
    {
        $post = Post::with(['tags'])->findOrFail($id);
        $series = Series::all();
        $selectedSeriesIds = $post->series->pluck('id')->toArray();

        return Inertia::render('private/posts/EditPage', [
            'post' => $post,
            'series' => $series,
            'selectedSeriesIds' => $selectedSeriesIds,
        ]);
    }

    public function update(UpdatePostRequest $request, int $id)
    {
        try {
            DB::beginTransaction();

            $post = Post::findOrFail($id);
            $validated = $request->validated();

            $post->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'content' => $validated['content'],
                'source_url' => $validated['source_url'] ?? null,
                'is_published' => $request->boolean('is_published'),
                'published_at' => $request->published_at,
            ]);

            if ($request->tags) {
                $tagIds = $this->getTagsInfo($request->tags);
                $post->tags()->sync($tagIds);
            } else {
                $post->tags()->sync([]);
            }

            $this->syncSeries($post, $request->series_ids);

            DB::commit();

            return redirect()->route('admin.index');
        } catch (Throwable $e) {
            DB::rollBack();

            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors('Update post failed');
        }
    }

    private function syncSeries(Post $post, ?array $seriesIds = [])
    {
        if (! $seriesIds) {
            $seriesIds = [];
        }

        $installedSeries = $post->series;

        foreach ($seriesIds as $seriesId) {
            $installed = $installedSeries->where('id', $seriesId)->first();

            if (! $installed) {
                $series = Series::find($seriesId);

                if (! $series) {
                    continue;
                }

                $order = $series->posts()->count() + 1;
                $post->series()->attach($seriesId, ['order' => $order]);
            }
        }

        $post->series()->sync($seriesIds);
    }

    public function destroy(int $id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json([
                'message' => 'Post Not Found',
            ], 404);
        }

        try {
            $post->tags()->detach();
            $post->delete();

            return redirect()->route('admin.index');
        } catch (Throwable $e) {
            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Delete Post Failed',
            ]);
        }
    }

    private function generateSlugForPost(string $title)
    {
        $slug = SlugHelpers::createFromString($title);

        $post = Post::where('slug', 'like', "$slug-%")
            ->orderBy('id', 'DESC')
            ->first();

        if ($post) {
            $index = Str::afterLast($post->slug, '-');

            if (is_numeric($index)) {
                $slug = SlugHelpers::createFromString($title, ((int) $index) + 1);
            } else {
                $slug = SlugHelpers::createFromString($post->slug, 1);
            }
        }

        return $slug;
    }

    private function getTagsInfo(array $tags)
    {
        $slugs = [];

        foreach ($tags as $tag) {
            $slugs[] = SlugHelpers::createFromString($tag);
        }

        $dbTags = Tag::whereIn('slug', $slugs)->get();
        $result = [];

        foreach ($slugs as $index => $slug) {
            $dbTag = $dbTags->where('slug', $slug)->first();

            if ($dbTag) {
                $result[] = $dbTag->id;
            } else {
                $dbTag = Tag::create([
                    'name' => $tags[$index],
                    'slug' => $slug,
                ]);

                $result[] = $dbTag->id;
            }
        }

        return $result;
    }
}
