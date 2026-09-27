<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Blog\Domain\Enums\SpecialTag;
use Modules\Blog\Infrastructure\Persistence\EloquentPost;

class Tag extends Model
{
    protected $fillable = [
        'id', 'name', 'slug',
    ];

    protected $appends = [
        'is_protected',
    ];

    public function getIsProtectedAttribute(): bool
    {
        return SpecialTag::isProtectedSlug((string) $this->slug);
    }

    /***** RELATIONSHIPS *****/
    public function posts()
    {
        return $this->belongsToMany(EloquentPost::class, 'post_tag', 'tag_id', 'post_id');
    }

    public function publicPosts()
    {
        return $this->belongsToMany(EloquentPost::class, 'post_tag', 'tag_id', 'post_id')
            ->visibleToGuest();
    }
}
