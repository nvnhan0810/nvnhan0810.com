<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    protected $table = 'series';

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    /***** RELATIONSHIPS *****/
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'series_posts', 'series_id', 'post_id')
            ->withPivot('order')
            ->orderBy('order');
    }
}
