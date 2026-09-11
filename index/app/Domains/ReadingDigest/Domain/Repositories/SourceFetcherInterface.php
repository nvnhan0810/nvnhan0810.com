<?php

namespace App\Domains\ReadingDigest\Domain\Repositories;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;

interface SourceFetcherInterface
{
    /**
     * @return \App\Domains\ReadingDigest\Application\DTOs\FetchedArticleDTO[]
     */
    public function fetch(SourceModel $source, int $limit = 50): array;
}
