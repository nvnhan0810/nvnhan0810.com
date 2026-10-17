<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetPageAnnotation;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetPageAnnotationHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly PageAnnotationRepository $annotations,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Query $query): StoredObjectResult
    {
        assert($query instanceof GetPageAnnotation);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $annotation = $this->annotations->findByDocumentPageAndFormat(
            $document->id,
            $query->pageIndex,
            $query->format,
        );
        if ($annotation === null || $annotation->isEmpty() || $annotation->seaweedKey === null) {
            throw ReaderDomainException::notFound('Annotation not found');
        }

        $contentType = $query->format->usesJsonPayload()
            ? ContentType::JSON
            : ContentType::OCTET_STREAM;

        return new StoredObjectResult(
            null,
            $this->storage->get($annotation->seaweedKey),
            $contentType,
            $annotation->revision,
            $annotation->contentSha256,
        );
    }
}
