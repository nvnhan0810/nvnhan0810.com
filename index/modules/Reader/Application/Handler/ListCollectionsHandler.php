<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListCollections;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListCollectionsHandler implements QueryHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    /** @return list<\Modules\Reader\Domain\Entities\Collection> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListCollections);

        return $this->collections->listForUser($query->userId);
    }
}
