<?php

namespace Modules\Blog\Infrastructure\Persistence;

use App\Models\Series;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Blog\Domain\Enums\PostStatus;

class EloquentPost extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'source_url',
        'published_at',
        'is_published',
        'status',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'status' => PostStatus::class,
    ];

    protected $appends = [
        'og_image_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (EloquentPost $post): void {
            $status = $post->status instanceof PostStatus
                ? $post->status
                : PostStatus::tryFrom((string) $post->status) ?? PostStatus::Draft;

            $post->is_published = $status === PostStatus::Public;
        });
    }

    public function getOgImageUrlAttribute(): string
    {
        return route('og.posts.show', ['slug' => $this->slug]);
    }

    public function scopeVisibleToGuest(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Public)
            ->whereDate('published_at', '<=', now());
    }

    public function scopeVisibleToViewer(Builder $query, bool $authenticated): Builder
    {
        if ($authenticated) {
            return $query;
        }

        return $query->visibleToGuest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')->withCount(['posts']);
    }

    public function publicTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')
            ->withCount(['posts' => function ($postQuery) {
                $postQuery->visibleToGuest();
            }]);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'series_posts', 'post_id', 'series_id')->orderBy('order');
    }
}
