<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\PutReadingProgress;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Entities\ReadingProgress;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ReadingProgressRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class PutReadingProgressHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ReadingProgressRepository $progress,
    ) {}

    public function handle(Command $command): ReadingProgress
    {
        assert($command instanceof PutReadingProgress);
        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        if ($command->pageIndex < 0) {
            throw ReaderDomainException::validation('page_index must be >= 0');
        }

        $existing = $this->progress->findByDocument($document->id, $command->userId);
        if (
            $existing !== null
            && $command->baseRevision !== null
            && $existing->revision !== $command->baseRevision
        ) {
            // v1: still accept LWW overwrite when client omits strict conflict preference —
            // only conflict when base_revision provided and mismatches.
            throw ReaderDomainException::conflict('Progress revision mismatch', [
                'server_revision' => $existing->revision,
            ]);
        }

        $now = new DateTimeImmutable('now');
        $entity = new ReadingProgress(
            documentId: $document->id,
            userId: $command->userId,
            pageIndex: $command->pageIndex,
            revision: ($existing?->revision ?? 0) + 1,
            updatedAt: $now,
        );

        $saved = $this->progress->save($entity);

        // Drive "recently read" ordering without bumping document revision.
        $this->documents->save(new Document(
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
            isFavorite: $document->isFavorite,
            revision: $document->revision,
            deletedAt: $document->deletedAt,
            lastOpenedAt: $now,
            createdAt: $document->createdAt,
            updatedAt: $document->updatedAt,
        ));

        return $saved;
    }
}
