<?php

namespace Modules\Blog\Infrastructure\Persistence;

use App\Models\Tag;
use Modules\Blog\Domain\Ports\SlugGenerator;
use Modules\Blog\Domain\Ports\TagRepository;

final class EloquentTagRepository implements TagRepository
{
    public function __construct(
        private readonly SlugGenerator $slugs,
    ) {}

    public function allWithPublicPostCount(): mixed
    {
        return Tag::withCount(['publicPosts'])->get();
    }

    public function resolveIdsFromNames(array $tagNames): array
    {
        $slugs = [];

        foreach ($tagNames as $tag) {
            $slugs[] = $this->slugs->fromString($tag);
        }

        $dbTags = Tag::whereIn('slug', $slugs)->get();
        $result = [];

        foreach ($slugs as $index => $slug) {
            $dbTag = $dbTags->where('slug', $slug)->first();

            if ($dbTag) {
                $result[] = (int) $dbTag->id;
            } else {
                $created = Tag::create([
                    'name' => $tagNames[$index],
                    'slug' => $slug,
                ]);

                $result[] = (int) $created->id;
            }
        }

        return $result;
    }
}
