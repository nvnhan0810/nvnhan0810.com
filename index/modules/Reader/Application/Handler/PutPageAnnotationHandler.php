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

        $existing = $this->annotations->findByDocumentPageAndFormat(
            $document->id,
            $command->pageIndex,
            $command->format,
        );
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
                format: $command->format,
                seaweedKey: null,
                byteSize: 0,
                contentSha256: null,
                revision: $nextRevision,
                updatedAt: $now,
            );

            return $this->annotations->save($annotation);
        }

        if ($command->format === AnnotationFormat::ReaderMarkupV1) {
            $this->assertValidMarkupJson($command->binary);
        }

        if (strlen($command->binary) > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('Annotation exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        $sha = $command->contentSha256 ?? hash('sha256', $command->binary);
        $contentType = $command->format === AnnotationFormat::ReaderMarkupV1
            ? ContentType::JSON
            : ContentType::OCTET_STREAM;
        $key = $command->format === AnnotationFormat::ReaderMarkupV1
            ? SeaweedObjectKey::markup($command->userId, $document->id, $command->pageIndex)
            : SeaweedObjectKey::drawing($command->userId, $document->id, $command->pageIndex);

        $this->storage->put($key, $command->binary, $contentType);

        $annotation = new PageAnnotation(
            id: $id,
            documentId: $document->id,
            userId: $command->userId,
            pageIndex: $command->pageIndex,
            format: $command->format,
            seaweedKey: $key,
            byteSize: strlen($command->binary),
            contentSha256: $sha,
            revision: $nextRevision,
            updatedAt: $now,
        );

        return $this->annotations->save($annotation);
    }

    private function assertValidMarkupJson(string $binary): void
    {
        try {
            $decoded = json_decode($binary, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ReaderDomainException::validation('markup must be valid JSON');
        }

        if (! is_array($decoded)) {
            throw ReaderDomainException::validation('markup root must be an object');
        }

        $version = $decoded['version'] ?? null;
        $items = $decoded['items'] ?? null;
        if ($version !== 1) {
            throw ReaderDomainException::validation('markup.version must be 1');
        }
        if (! is_array($items)) {
            throw ReaderDomainException::validation('markup.items must be an array');
        }
    }
}
