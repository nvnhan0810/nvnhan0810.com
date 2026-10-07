<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\UpdateDocument;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class UpdateDocumentHandler implements CommandHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof UpdateDocument);
        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $title = trim($command->title);
        if ($title === '') {
            throw ReaderDomainException::validation('title is required');
        }

        if (
            $command->baseRevision !== null
            && $document->revision !== $command->baseRevision
        ) {
            throw ReaderDomainException::conflict('Document revision mismatch', [
                'server_revision' => $document->revision,
            ]);
        }

        $updated = new Document(
            id: $document->id,
            userId: $document->userId,
            title: $title,
            pageCount: $document->pageCount,
            contentType: $document->contentType,
            byteSize: $document->byteSize,
            contentSha256: $document->contentSha256,
            seaweedPdfKey: $document->seaweedPdfKey,
            seaweedThumbKey: $document->seaweedThumbKey,
            status: $document->status,
            isFavorite: $document->isFavorite,
            revision: $document->revision + 1,
            deletedAt: $document->deletedAt,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: new DateTimeImmutable('now'),
        );

        return $this->documents->save($updated);
    }
}
