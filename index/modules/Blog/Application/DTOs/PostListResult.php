<?php

namespace Modules\Blog\Application\DTOs;

final class PostListResult
{
    /**
     * @param  mixed  $posts  LengthAwarePaginator
     * @param  array{search: string|null, tag: string|null, status: string|null}  $filters
     * @param  list<int>  $selectedSeriesIds
     */
    public function __construct(
        public readonly mixed $posts,
        public readonly mixed $tags,
        public readonly array $filters,
        public readonly mixed $series,
        public readonly mixed $editingPost,
        public readonly array $selectedSeriesIds,
        public readonly bool $canManage,
    ) {}
}
