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
        private readonly DevToApiAdapter $devToApi,
    ) {}

    public function for(SourceModel $source): SourceFetcherInterface
    {
        if (DevToApiAdapter::supportsUrl($source->url)) {
            return $this->devToApi;
        }

        return match (SourceType::tryFrom($source->type)) {
            SourceType::Rss, SourceType::Reddit, SourceType::GithubBlog => $this->rss,
            SourceType::HnAlgolia => $this->hn,
            SourceType::CustomHtml => $this->rss,
            default => throw new InvalidArgumentException("Unsupported source type: {$source->type}"),
        };
    }
}
