<?php

namespace Modules\ReadingDigest\Infrastructure\Sources;

use App\Models\RdSource;
use InvalidArgumentException;
use Modules\ReadingDigest\Domain\Enums\SourceType;
use Modules\ReadingDigest\Domain\Repositories\SourceFetcherInterface;

class SourceFetcherRegistry
{
    public function __construct(
        private readonly RssSourceAdapter $rss,
        private readonly HackerNewsAlgoliaAdapter $hn,
        private readonly DevToApiAdapter $devToApi,
    ) {}

    public function for(RdSource $source): SourceFetcherInterface
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
