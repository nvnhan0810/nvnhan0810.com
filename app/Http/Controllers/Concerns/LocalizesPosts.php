<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait LocalizesPosts
{
    protected function currentLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, Post::SUPPORTED_LOCALES, true)
            ? $locale
            : Post::DEFAULT_LOCALE;
    }

    protected function localizePaginator(LengthAwarePaginator $paginator, ?string $locale = null): LengthAwarePaginator
    {
        $locale = $locale ?? $this->currentLocale();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Post $post) => $this->localizedPostModel($post, $locale))
                ->filter()
                ->values()
        );

        return $paginator;
    }

    protected function localizedPostModel(Post $post, ?string $locale = null): ?Post
    {
        $locale = $locale ?? $this->currentLocale();
        $localized = $post->toLocalizedArray($locale);

        if (! $localized) {
            return null;
        }

        return $post->forceFill([
            'title' => $localized['title'],
            'description' => $localized['description'],
            'content' => $localized['content'],
        ]);
    }

    protected function localizeSeriesPosts(Collection $series, ?string $locale = null): Collection
    {
        $locale = $locale ?? $this->currentLocale();

        return $series->map(function ($item) use ($locale) {
            if ($item->relationLoaded('posts')) {
                $item->setRelation(
                    'posts',
                    $item->posts
                        ->map(fn (Post $post) => $this->localizedPostModel($post, $locale))
                        ->filter()
                        ->values()
                );
            }

            return $item;
        });
    }
}
