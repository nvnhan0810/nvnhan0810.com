<?php

namespace App\Domains\ReadingDigest\Infrastructure\Sources;

use App\Domains\ReadingDigest\Domain\Enums\SourceType;
use App\Domains\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use InvalidArgumentException;

class SourceFetcherRegistry
{
    public function __construct(
        private readonly RssSourceAdapter $rss,
        private readonly HackerNewsAlgoliaAdapter $hn,
    ) {}

    public function for(SourceModel $source): SourceFetcherInterface
    {
        return match (SourceType::tryFrom($source->type)) {
            SourceType::Rss, SourceType::Reddit, SourceType::GithubBlog => $this->rss,
            SourceType::HnAlgolia => $this->hn,
            SourceType::CustomHtml => $this->rss,
            default => throw new InvalidArgumentException("Unsupported source type: {$source->type}"),
        };
    }
}
