<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\UploadThumbnail;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\ValueObjects\SeaweedObjectKey;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class UploadThumbnailHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof UploadThumbnail);

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        if ($command->byteSize > 5 * 1024 * 1024) {
            throw ReaderDomainException::payloadTooLarge('Thumbnail exceeds size limit');
        }

        $key = SeaweedObjectKey::thumbnail($command->userId, $command->documentId);
        $this->storage->put($key, $command->contents, ContentType::JPEG);

        $now = new DateTimeImmutable('now');
        $updated = new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: $document->pageCount,
            contentType: $document->contentType,
            byteSize: $document->byteSize,
            contentSha256: $document->contentSha256,
            seaweedPdfKey: $document->seaweedPdfKey,
            seaweedThumbKey: $key,
            status: $document->status,
            isFavorite: $document->isFavorite,
            revision: $document->revision + 1,
            deletedAt: $document->deletedAt,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: $now,
        );

        return $this->documents->save($updated);
    }
}
