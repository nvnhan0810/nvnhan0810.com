<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListDocumentCollectionIds;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListDocumentCollectionIdsHandler implements QueryHandler
{
    public function __construct(
        private readonly CollectionRepository $collections,
        private readonly DocumentRepository $documents,
    ) {}

    /** @return list<string> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListDocumentCollectionIds);

        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        return $this->collections->listCollectionIdsForDocument(
            $query->documentId,
            $query->userId,
        );
    }
}
