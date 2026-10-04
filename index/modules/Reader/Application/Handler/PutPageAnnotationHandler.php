<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\PutPageAnnotation;
use Modules\Reader\Domain\Entities\PageAnnotation;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Reader\Domain\ValueObjects\SeaweedObjectKey;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Ramsey\Uuid\Uuid;

final class PutPageAnnotationHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly PageAnnotationRepository $annotations,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Command $command): PageAnnotation
    {
        assert($command instanceof PutPageAnnotation);

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        if ($command->pageIndex < 0) {
            throw ReaderDomainException::validation('page_index must be >= 0');
        }

        $existing = $this->annotations->findByDocumentAndPage($document->id, $command->pageIndex);
        if ($existing !== null && $command->baseRevision !== null && $existing->revision !== $command->baseRevision) {
            throw ReaderDomainException::conflict('Annotation revision mismatch', [
                'server_revision' => $existing->revision,
            ]);
        }

        $maxBytes = (int) config('reader.max_drawing_bytes');
        $now = new DateTimeImmutable('now');
        $nextRevision = ($existing?->revision ?? 0) + 1;
        $id = $existing?->id ?? Uuid::uuid4()->toString();

        if ($command->empty || $command->binary === null || $command->binary === '') {
            if ($existing?->seaweedKey !== null) {
                $this->storage->delete($existing->seaweedKey);
            }

            $annotation = new PageAnnotation(
                id: $id,
                documentId: $document->id,
                userId: $command->userId,
                pageIndex: $command->pageIndex,
                format: AnnotationFormat::PencilKitDrawing,
                seaweedKey: null,
                byteSize: 0,
                contentSha256: null,
                revision: $nextRevision,
                updatedAt: $now,
            );

            return $this->annotations->save($annotation);
        }

        if (strlen($command->binary) > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('Drawing exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        $sha = $command->contentSha256 ?? hash('sha256', $command->binary);
        $key = SeaweedObjectKey::drawing($command->userId, $document->id, $command->pageIndex);
        $this->storage->put($key, $command->binary, ContentType::OCTET_STREAM);

        $annotation = new PageAnnotation(
            id: $id,
            documentId: $document->id,
            userId: $command->userId,
            pageIndex: $command->pageIndex,
            format: AnnotationFormat::PencilKitDrawing,
            seaweedKey: $key,
            byteSize: strlen($command->binary),
            contentSha256: $sha,
            revision: $nextRevision,
            updatedAt: $now,
        );

        return $this->annotations->save($annotation);
    }
}
