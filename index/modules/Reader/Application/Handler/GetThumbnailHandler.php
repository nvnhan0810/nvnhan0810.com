<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetThumbnail;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetThumbnailHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Query $query): StoredObjectResult
    {
        assert($query instanceof GetThumbnail);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null || $document->seaweedThumbKey === null) {
            throw ReaderDomainException::notFound('Thumbnail not found');
        }

        $ttl = (int) config('reader.signed_url_ttl_seconds', 900);
        $url = $this->storage->temporaryUrl(
            $document->seaweedThumbKey,
            new DateTimeImmutable('+'.$ttl.' seconds'),
        );

        if ($url !== null) {
            return new StoredObjectResult($url, null, ContentType::JPEG);
        }

        return new StoredObjectResult(null, $this->storage->get($document->seaweedThumbKey), ContentType::JPEG);
    }
}
