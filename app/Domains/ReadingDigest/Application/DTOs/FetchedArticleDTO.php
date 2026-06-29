<?php

namespace App\Domains\ReadingDigest\Application\DTOs;

class FetchedArticleDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $summary,
        public readonly ?string $contentText,
        public readonly ?string $contentHtml,
        public readonly ?\DateTimeInterface $publishedAt,
        public readonly array $rawTags = [],
        public readonly ?string $language = 'en',
    ) {}
}
