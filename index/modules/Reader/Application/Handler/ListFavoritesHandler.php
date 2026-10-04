<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListFavorites;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListFavoritesHandler implements QueryHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    /** @return list<\Modules\Reader\Domain\Entities\Document> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListFavorites);

        return $this->documents->listFavoritesForUser($query->userId);
    }
}
