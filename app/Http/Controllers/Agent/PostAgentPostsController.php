<?php

namespace App\Http\Controllers\Agent;

use App\Helpers\SlugHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Series;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostAgentPostsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isPublished = $request->string('is_published');
        $query = Post::query()->orderByDesc('created_at');

        if ($isPublished !== null) {
            $value = filter_var($isPublished, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $query->where('is_published', $value);
            }
        }

        $posts = $query->paginate(25);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (Post $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'is_published' => $p->is_published,
                'published_at' => optional($p->published_at)->toISOString(),
                'updated_at' => optional($p->updated_at)->toISOString(),
            ])->values(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $post = Post::with(['tags', 'series', 'translations'])->find($id);

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        return response()->json([
            'id' => $post->id,
            'slug' => $post->slug,
            'is_published' => (bool) $post->is_published,
            'published_at' => optional($post->published_at)->toISOString(),
            'translations' => $post->translations
                ? $post->translations->mapWithKeys(fn (PostTranslation $t) => [
                    $t->locale => [
                        'locale' => $t->locale,
                        'title' => $t->title,
                        'description' => $t->description,
                        'content' => $t->content,
                        'source_url' => $t->source_url,
                    ],
                ])->all()
                : [],
            'tags' => $post->tags->map(fn (Tag $t) => $t->name)->values()->all(),
            'series_ids' => $post->series->pluck('id')->values()->all(),
            'edit_url' => url("/admin/posts/{$post->id}/edit"),
        ]);
    }

    public function createDraft(CreatePostRequest $request): JsonResponse
    {
        $translations = $request->validated('translations');

        $slugSourceTitle = $translations['en']['title'] ?? ($translations['vi']['title'] ?? '');
        $slugSourceTitle = is_string($slugSourceTitle) ? trim($slugSourceTitle) : '';

        if ($slugSourceTitle === '') {
            return response()->json([
                'message' => 'Không tạo được slug: thiếu title ở locale en/vi',
            ], 422);
        }

        $publishedAt = $request->published_at ?? now();

        try {
            DB::beginTransaction();

            $slug = $this->generateSlugForPost($slugSourceTitle);

            $post = Post::create([
                'slug' => $slug,
                'is_published' => false,
                'published_at' => $publishedAt,
            ]);

            $this->syncTranslations($post, $translations);

            if ($request->tags) {
                $tagIds = $this->getTagsInfo($request->tags);
                $post->tags()->sync($tagIds);
            }

            $this->syncSeries($post, $request->series_ids);

            DB::commit();

            return response()->json([
                'id' => $post->id,
                'edit_url' => url("/admin/posts/{$post->id}/edit"),
                'is_published' => (bool) $post->is_published,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::info(__METHOD__, ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Create draft failed'], 500);
        }
    }

    public function update(int $id, UpdatePostRequest $request): JsonResponse
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $translations = $request->validated('translations');

        try {
            DB::beginTransaction();

            $post->update([
                'is_published' => $request->boolean('is_published'),
                'published_at' => $request->published_at,
            ]);

            $this->syncTranslations($post, $translations);

            if ($request->tags) {
                $tagIds = $this->getTagsInfo($request->tags);
                $post->tags()->sync($tagIds);
            } else {
                $post->tags()->sync([]);
            }

            $this->syncSeries($post, $request->series_ids);

            DB::commit();

            return response()->json([
                'id' => $post->id,
                'edit_url' => url("/admin/posts/{$post->id}/edit"),
                'is_published' => (bool) $post->is_published,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::info(__METHOD__, ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Update post failed'], 500);
        }
    }

    public function publish(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'confirmed' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (! (bool) $validated['confirmed']) {
            return response()->json([
                'message' => 'Chưa confirmed = false',
            ], 422);
        }

        $post = Post::find($id);

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $publishedAt = $validated['published_at'] ?? now();

        try {
            DB::beginTransaction();
            $post->update([
                'is_published' => true,
                'published_at' => $publishedAt,
            ]);
            DB::commit();

            return response()->json([
                'id' => $post->id,
                'edit_url' => url("/admin/posts/{$post->id}/edit"),
                'is_published' => (bool) $post->is_published,
                'published_at' => optional($post->published_at)->toISOString(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::info(__METHOD__, ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Publish failed'], 500);
        }
    }

    private function syncTranslations(Post $post, array $translations): void
    {
        foreach (Post::SUPPORTED_LOCALES as $locale) {
            if (! isset($translations[$locale])) {
                PostTranslation::where('post_id', $post->id)
                    ->where('locale', $locale)
                    ->delete();

                continue;
            }

            $data = $translations[$locale];

            PostTranslation::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'content' => $data['content'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                ]
            );
        }
    }

    private function syncSeries(Post $post, ?array $seriesIds = []): void
    {
        if ($seriesIds === null) {
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

    private function generateSlugForPost(string $title): string
    {
        $slug = SlugHelpers::createFromString($title);

        $post = Post::where('slug', 'like', "$slug-%")
            ->orderBy('id', 'DESC')
            ->first();

        if ($post) {
            $index = \Illuminate\Support\Str::afterLast($post->slug, '-');

            if (is_numeric($index)) {
                $slug = SlugHelpers::createFromString($title, ((int) $index) + 1);
            } else {
                $slug = SlugHelpers::createFromString($post->slug, 1);
            }
        }

        return $slug;
    }

    private function getTagsInfo(array $tags): array
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

