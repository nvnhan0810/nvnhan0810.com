<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Inertia\Inertia;

class HomeController extends Controller
{
    public const LATEST_POSTS_LIMIT = 10;

    public function __invoke()
    {
        $posts = Post::query()
            ->with(['publicTags'])
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(self::LATEST_POSTS_LIMIT)
            ->get();

        return Inertia::render('public/HomePage', [
            'posts' => $posts,
        ]);
    }
}
