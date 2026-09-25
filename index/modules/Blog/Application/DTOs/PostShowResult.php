<?php

namespace Modules\Blog\Application\DTOs;

final class PostShowResult
{
    /**
     * @param  list<int>  $selectedSeriesIds
     */
    public function __construct(
        public readonly mixed $post,
        public readonly mixed $series,
        public readonly mixed $editorSeries,
        public readonly array $selectedSeriesIds,
        public readonly bool $canManage,
    ) {}
}
