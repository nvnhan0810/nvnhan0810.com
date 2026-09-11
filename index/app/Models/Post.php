<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'source_url',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    protected $appends = [
        'og_image_url',
    ];

    public function getOgImageUrlAttribute(): string
    {
        return route('og.posts.show', ['slug' => $this->slug]);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')->withCount(['posts']);
    }

    public function publicTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')
            ->withCount(['posts' => function ($postQuery) {
                $postQuery->where('is_published', true)->whereDate('published_at', '<=', now());
            }]);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'series_posts', 'post_id', 'series_id')->orderBy('order');
    }
}
