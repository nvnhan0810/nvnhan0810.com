<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\GetCollection;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetCollectionHandler implements QueryHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    public function handle(Query $query): Collection
    {
        assert($query instanceof GetCollection);

        $collection = $this->collections->findByIdForUser($query->collectionId, $query->userId);
        if ($collection === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        return $collection;
    }
}
