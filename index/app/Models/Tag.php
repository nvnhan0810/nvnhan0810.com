<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'id', 'name', 'slug',
    ];

    /***** RELATIONSHIPS *****/
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }

    public function publicPosts()
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id')
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now());
    }
}
