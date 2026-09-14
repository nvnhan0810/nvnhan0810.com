<?php

namespace Modules\ReadingDigest\Domain\Repositories;

use App\Models\RdSource;
use Modules\ReadingDigest\Application\DTOs\FetchedArticleDTO;

interface SourceFetcherInterface
{
    /**
     * @return FetchedArticleDTO[]
     */
    public function fetch(RdSource $source, int $limit = 50): array;
}
