<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListCollectionDocuments;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListCollectionDocumentsHandler implements QueryHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    /** @return list<\Modules\Reader\Domain\Entities\Document> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListCollectionDocuments);

        if ($this->collections->findByIdForUser($query->collectionId, $query->userId) === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        return $this->collections->listDocuments($query->collectionId, $query->userId);
    }
}
