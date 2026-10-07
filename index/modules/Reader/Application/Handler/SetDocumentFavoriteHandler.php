<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\SetDocumentFavorite;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class SetDocumentFavoriteHandler implements CommandHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof SetDocumentFavorite);

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        if ($document->isFavorite === $command->favorite) {
            return $document;
        }

        $now = new DateTimeImmutable('now');

        return $this->documents->save(new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: $document->pageCount,
            contentType: $document->contentType,
            byteSize: $document->byteSize,
            contentSha256: $document->contentSha256,
            seaweedPdfKey: $document->seaweedPdfKey,
            seaweedThumbKey: $document->seaweedThumbKey,
            status: $document->status,
            isFavorite: $command->favorite,
            revision: $document->revision + 1,
            deletedAt: $document->deletedAt,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: $now,
        ));
    }
}
