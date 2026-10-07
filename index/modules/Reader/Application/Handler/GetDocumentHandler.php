<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\GetDocument;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetDocumentHandler implements QueryHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function handle(Query $query): Document
    {
        assert($query instanceof GetDocument);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        return $document;
    }
}
