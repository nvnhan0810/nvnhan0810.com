<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetDocumentFile;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetDocumentFileHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Query $query): StoredObjectResult
    {
        assert($query instanceof GetDocumentFile);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null || $document->seaweedPdfKey === null) {
            throw ReaderDomainException::notFound('Document file not found');
        }

        $ttl = (int) config('reader.signed_url_ttl_seconds', 900);
        if (! $query->preferBinary) {
            $url = $this->storage->temporaryUrl(
                $document->seaweedPdfKey,
                new DateTimeImmutable('+'.$ttl.' seconds'),
            );

            if ($url !== null) {
                return new StoredObjectResult($url, null, ContentType::PDF, $document->revision, $document->contentSha256);
            }
        }

        return new StoredObjectResult(
            null,
            $this->storage->get($document->seaweedPdfKey),
            ContentType::PDF,
            $document->revision,
            $document->contentSha256,
        );
    }
}
