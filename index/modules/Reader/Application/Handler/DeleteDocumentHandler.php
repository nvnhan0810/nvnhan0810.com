<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\DeleteDocument;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class DeleteDocumentHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
    ) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof DeleteDocument);
        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        // Soft delete only — keep Seaweed objects so trash restore works.
        // Hard purge (trash API / retention cron) removes blobs + rows.
        $now = new DateTimeImmutable('now');
        $deleted = new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: $document->pageCount,
            contentType: $document->contentType,
            byteSize: $document->byteSize,
            contentSha256: $document->contentSha256,
            seaweedPdfKey: $document->seaweedPdfKey,
            seaweedThumbKey: $document->seaweedThumbKey,
            status: DocumentStatus::Deleted,
            isFavorite: $document->isFavorite,
            revision: $document->revision + 1,
            deletedAt: $now,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: $now,
        );

        return $this->documents->save($deleted);
    }
}
