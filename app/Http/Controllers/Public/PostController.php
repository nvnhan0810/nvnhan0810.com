<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\LocalizesPosts;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    use LocalizesPosts;

    public function index(Request $request)
    {
        $search = $request->search;
        $tag = $request->tag;
        $locale = $this->currentLocale();

        $data = Post::with(['publicTags', 'translations'])
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->whereHas('translations')
            ->when($search, function ($searchQuery) use ($search, $locale) {
                $searchQuery->whereHas('translations', function ($translationQuery) use ($search, $locale) {
                    $translationQuery
                        ->where(function ($localeQuery) use ($locale) {
                            $localeQuery
                                ->where('locale', $locale)
                                ->orWhere('locale', Post::DEFAULT_LOCALE);
                        })
                        ->where('title', 'LIKE', "%{$search}%");
                });
            })
            ->when($tag, function ($tagQuery) use ($tag) {
                $tagQuery->whereHas('publicTags', function ($tagQuery) use ($tag) {
                    $tagQuery->where('slug', $tag);
                });
            })
            ->orderBy('published_at', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate(50);

        $this->localizePaginator($data, $locale);

        $tags = Tag::withCount(['publicPosts'])->get();

        return Inertia::render('public/posts/ListPage', [
            'posts' => $data,
            'tags' => $tags,
        ]);
    }

    public function show(string $slug)
    {
        $locale = $this->currentLocale();

        $post = Post::with(['publicTags', 'translations'])
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->where('slug', $slug)
            ->whereHas('translations')
            ->firstOrFail();

        $series = Series::with(['posts.translations'])->whereHas('posts', function ($query) use ($post) {
            $query->where('post_id', $post->id);
        })->get();

        $this->localizeSeriesPosts($series, $locale);

        $localized = $post->toLocalizedArray($locale);

        if (! $localized) {
            abort(404);
        }

        return Inertia::render('public/posts/ShowPage', [
            'post' => $localized,
            'series' => $series,
        ]);
    }
}
