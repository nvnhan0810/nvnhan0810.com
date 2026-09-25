<?php

namespace Modules\Blog\Infrastructure\Slug;

use App\Helpers\SlugHelpers;
use Illuminate\Support\Str;
use Modules\Blog\Domain\Ports\SlugGenerator;
use Modules\Blog\Infrastructure\Persistence\EloquentPost;

final class LaravelSlugGenerator implements SlugGenerator
{
    public function fromString(string $value, int $index = 0): string
    {
        return SlugHelpers::createFromString($value, $index);
    }

    public function uniqueForPost(string $title): string
    {
        $slug = $this->fromString($title);

        $post = EloquentPost::query()
            ->where('slug', 'like', "{$slug}-%")
            ->orderByDesc('id')
            ->first();

        if ($post === null) {
            return $slug;
        }

        $index = Str::afterLast($post->slug, '-');

        if (is_numeric($index)) {
            return $this->fromString($title, ((int) $index) + 1);
        }

        return $this->fromString($post->slug, 1);
    }
}
