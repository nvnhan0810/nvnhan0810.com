<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Post extends Model
{
    public const DEFAULT_LOCALE = 'en';

    public const SUPPORTED_LOCALES = ['en', 'vi'];

    protected $fillable = [
        'slug',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function translate(?string $locale = null): PostTranslation
    {
        $locale = $locale ?? app()->getLocale();

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $translation = $this->translations->firstWhere('locale', $locale);

        if (! $translation) {
            $translation = $this->translations->firstWhere('locale', self::DEFAULT_LOCALE);
        }

        if (! $translation) {
            $translation = $this->translations->first();
        }

        if (! $translation) {
            abort(404, 'Post translation not found');
        }

        return $translation;
    }

    public function toLocalizedArray(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $translation = $this->translate($locale);

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $translation->title,
            'description' => $translation->description,
            'content' => $translation->content,
            'source_url' => $translation->source_url,
            'published_at' => $this->published_at,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->relationLoaded('publicTags')) {
            $data['public_tags'] = $this->publicTags;
        }

        if ($this->relationLoaded('tags')) {
            $data['tags'] = $this->tags;
        }

        if ($this->relationLoaded('translations')) {
            $data['translations'] = $this->translations
                ->mapWithKeys(fn (PostTranslation $item) => [
                    $item->locale => [
                        'locale' => $item->locale,
                        'title' => $item->title,
                        'description' => $item->description,
                        'content' => $item->content,
                        'source_url' => $item->source_url,
                    ],
                ])
                ->all();
        }

        $data['og_image_url'] = route('og.posts.show', [
            'slug' => $this->slug,
            'locale' => $locale,
        ]);

        return $data;
    }

    public static function mapLocalizedCollection(Collection $posts, ?string $locale = null): array
    {
        return $posts
            ->map(fn (Post $post) => $post->toLocalizedArray($locale))
            ->values()
            ->all();
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
