<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $data = Tag::withCount(['publicPosts'])->get();

        return response()->json([
            'message' => 'Fetch Tags Successfully!',
            'data' => $data,
        ]);
    }

    public function show(string $slug)
    {
        $tag = Tag::withCount(['publicPosts'])->where('slug', $slug)->first();

        if (!$tag) {
            return response()->json([
                'message' => 'Tag Not Found',
            ], 404);
        }

        return response()->json([
            'message' => 'Fetch Tag Successfully!',
            'data' => $tag,
        ]);
    }

    public function getPosts(Request $request, string $slug)
    {
        $search = $request->search;

        $posts = Post::where('is_published', true)->whereHas('publicTags', function($tagQuery) use ($slug) {
            $tagQuery->where('slug', $slug);
        })->when($search, function ($searchQuery) use ($search) {
            $searchQuery->where('title', 'LIKE', "%{$search}%");
        })->paginate(50);

        return response()->json([
            'message' => 'Fetch Tag Successfully!',
            ...$posts->toArray(),
        ]);
    }
}
