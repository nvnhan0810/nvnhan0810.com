<?php

namespace Modules\Blog\Infrastructure\Persistence;

use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Domain\Enums\PostStatus;
use Modules\Blog\Domain\Exceptions\PostNotFoundException;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Blog\Domain\Ports\TagRepository;

final class EloquentPostRepository implements PostRepository
{
    public function __construct(
        private readonly TagRepository $tags,
    ) {}

    public function paginateVisible(
        bool $authenticated,
        string $search,
        string $tag,
        ?string $statusFilter,
        int $perPage = 50,
    ): mixed {
        return EloquentPost::query()
            ->with($authenticated ? ['tags', 'publicTags'] : ['publicTags'])
            ->visibleToViewer($authenticated)
            ->when($statusFilter !== null, function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->when($search !== '', function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'LIKE', "%{$search}%");
            })
            ->when($tag !== '', function ($tagQuery) use ($tag, $authenticated) {
                $relation = $authenticated ? 'tags' : 'publicTags';
                $tagQuery->whereHas($relation, function ($inner) use ($tag) {
                    $inner->where('slug', $tag);
                });
            })
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findVisibleBySlug(string $slug, bool $authenticated): mixed
    {
        return EloquentPost::with($authenticated ? ['tags', 'publicTags', 'series'] : ['publicTags'])
            ->visibleToViewer($authenticated)
            ->where('slug', $slug)
            ->first();
    }

    public function findForEdit(int $id): mixed
    {
        return EloquentPost::with(['tags', 'series'])->find($id);
    }

    public function findById(int $id): mixed
    {
        return EloquentPost::query()->find($id);
    }

    public function latestVisible(int $limit): mixed
    {
        return EloquentPost::query()
            ->with(['publicTags'])
            ->visibleToGuest()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function allVisibleForSitemap(): mixed
    {
        return EloquentPost::query()
            ->visibleToGuest()
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);
    }

    public function createWithRelations(array $attributes, ?array $tagNames, array $seriesIds): mixed
    {
        return DB::transaction(function () use ($attributes, $tagNames, $seriesIds) {
            $post = EloquentPost::create([
                'slug' => $attributes['slug'],
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'content' => $attributes['content'],
                'source_url' => $attributes['source_url'] ?? null,
                'status' => $attributes['status'] instanceof PostStatus
                    ? $attributes['status']
                    : PostStatus::from((string) $attributes['status']),
                'published_at' => $attributes['published_at'] ?? now(),
            ]);

            if (! empty($tagNames)) {
                $post->tags()->sync($this->tags->resolveIdsFromNames($tagNames));
            }

            $this->syncSeriesOn($post, $seriesIds);

            return $post;
        });
    }

    public function updateWithRelations(int $id, array $attributes, ?array $tagNames, array $seriesIds): mixed
    {
        return DB::transaction(function () use ($id, $attributes, $tagNames, $seriesIds) {
            $post = EloquentPost::with('series')->find($id);

            if ($post === null) {
                throw new PostNotFoundException($id);
            }

            $post->update([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'content' => $attributes['content'],
                'source_url' => $attributes['source_url'] ?? null,
                'status' => $attributes['status'] instanceof PostStatus
                    ? $attributes['status']
                    : PostStatus::from((string) $attributes['status']),
                'published_at' => $attributes['published_at'] ?? $post->published_at ?? now(),
            ]);

            $tagIds = ! empty($tagNames)
                ? $this->tags->resolveIdsFromNames($tagNames)
                : [];
            $post->tags()->sync($tagIds);
            $this->syncSeriesOn($post, $seriesIds);

            return $post->fresh(['tags', 'series']);
        });
    }

    public function delete(int $id): void
    {
        $post = EloquentPost::query()->find($id);

        if ($post === null) {
            throw new PostNotFoundException($id);
        }

        $post->tags()->detach();
        $post->delete();
    }

    /**
     * @param  list<int>  $seriesIds
     */
    private function syncSeriesOn(EloquentPost $post, array $seriesIds): void
    {
        $seriesIds = $seriesIds ?: [];
        $installedSeries = $post->relationLoaded('series')
            ? $post->series
            : $post->series()->get();

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
}
