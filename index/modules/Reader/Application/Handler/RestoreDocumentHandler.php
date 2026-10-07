<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\RestoreDocument;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class RestoreDocumentHandler implements CommandHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof RestoreDocument);

        $document = $this->documents->findByIdForUser(
            $command->documentId,
            $command->userId,
            includeDeleted: true,
        );

        if ($document === null || $document->deletedAt === null) {
            throw ReaderDomainException::notFound('Trashed document not found');
        }

        $hasFile = $document->seaweedPdfKey !== null && $document->seaweedPdfKey !== '';
        $now = new DateTimeImmutable('now');

        $restored = new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: $document->pageCount,
            contentType: $document->contentType,
            byteSize: $document->byteSize,
            contentSha256: $document->contentSha256,
            seaweedPdfKey: $document->seaweedPdfKey,
            seaweedThumbKey: $document->seaweedThumbKey,
            status: $hasFile ? DocumentStatus::Ready : DocumentStatus::Uploading,
            isFavorite: $document->isFavorite,
            revision: $document->revision + 1,
            deletedAt: null,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: $now,
        );

        return $this->documents->save($restored);
    }
}
