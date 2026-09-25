<?php

namespace Modules\Blog\Domain\Ports;

interface TagRepository
{
    /**
     * @return mixed Collection of tags with publicPosts count
     */
    public function allWithPublicPostCount(): mixed;

    /**
     * Resolve existing tags by slug or create missing ones.
     *
     * @param  list<string>  $tagNames
     * @return list<int>
     */
    public function resolveIdsFromNames(array $tagNames): array;
}
