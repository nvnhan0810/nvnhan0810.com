<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        $tag = $request->tag;

        $data = Post::with(['publicTags'])
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->when($search, function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'LIKE', "%{$search}%");
            })
            ->when($tag, function ($tagQuery) use ($tag) {
                $tagQuery->whereHas('publicTags', function ($tagQuery) use ($tag) {
                    $tagQuery->where('slug', $tag);
                });
            })
            ->orderBy('published_at', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate(50);

        $tags = Tag::withCount(['publicPosts'])->get();

        return Inertia::render('public/posts/ListPage', [
            'posts' => $data,
            'tags' => $tags,
        ]);
    }

    public function show(string $slug)
    {
        $post = Post::with(['publicTags'])
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->where('slug', $slug)
            ->firstOrFail();

        $series = Series::with(['posts' => function ($query) {
            $query->where('is_published', true)
                ->whereDate('published_at', '<=', now());
        }])->whereHas('posts', function ($query) use ($post) {
            $query->where('post_id', $post->id);
        })->get();

        return Inertia::render('public/posts/ShowPage', [
            'post' => $post,
            'series' => $series,
        ]);
    }
}
