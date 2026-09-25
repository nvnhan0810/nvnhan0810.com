<?php

namespace Modules\Blog\Application\Command;

use Modules\Shared\Application\Command;

final class CreatePost implements Command
{
    /**
     * @param  list<string>|null  $tags
     * @param  list<int>|null  $seriesIds
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $status,
        public readonly ?string $description = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $publishedAt = null,
        public readonly ?array $tags = null,
        public readonly ?array $seriesIds = null,
    ) {}
}
