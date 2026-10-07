<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Service;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\ValueObjects\SeaweedObjectKey;
use Ramsey\Uuid\Uuid;

/**
 * Create a document, store PDF bytes, optionally favorite + attach collections.
 */
final class DocumentIngester
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly CollectionRepository $collections,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  list<string>  $collectionIds
     */
    public function ingest(
        string $userId,
        string $title,
        string $contents,
        int $pageCount,
        array $collectionIds,
        bool $isFavorite,
    ): Document {
        $maxBytes = (int) config('reader.max_pdf_bytes');
        $byteSize = strlen($contents);

        if ($byteSize > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('PDF exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        if (! str_starts_with($contents, '%PDF')) {
            throw ReaderDomainException::validation('File is not a PDF');
        }

        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            throw ReaderDomainException::validation('title is required');
        }

        $resolvedPageCount = $pageCount > 0 ? $pageCount : PdfPageCounter::count($contents);
        $id = Uuid::uuid4()->toString();
        $sha256 = hash('sha256', $contents);
        $key = SeaweedObjectKey::pdf($userId, $id);

        $this->storage->put($key, $contents, ContentType::PDF);

        $now = new DateTimeImmutable('now');
        $document = $this->documents->create(
            $id,
            $userId,
            $trimmedTitle,
            max(0, $resolvedPageCount),
            DocumentStatus::Ready,
        );

        $document = $this->documents->save(new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: max(0, $resolvedPageCount),
            contentType: ContentType::PDF,
            byteSize: $byteSize,
            contentSha256: $sha256,
            seaweedPdfKey: $key,
            seaweedThumbKey: null,
            status: DocumentStatus::Ready,
            isFavorite: $isFavorite,
            revision: $document->revision + 1,
            deletedAt: null,
            lastOpenedAt: null,
            createdAt: $document->createdAt,
            updatedAt: $now,
        ));

        $this->attachCollections($userId, $document->id, $collectionIds);

        return $document;
    }

    /**
     * Ingest from PHP upload temp file — streams to storage (no full-file string in memory).
     *
     * @param  list<string>  $collectionIds
     */
    public function ingestFromLocalPath(
        string $userId,
        string $title,
        string $localPath,
        int $byteSize,
        int $pageCount,
        array $collectionIds,
        bool $isFavorite,
    ): Document {
        $maxBytes = (int) config('reader.max_pdf_bytes');
        if ($byteSize > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('PDF exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        if ($byteSize <= 0 || ! is_readable($localPath)) {
            throw ReaderDomainException::validation('Unable to read uploaded file');
        }

        $head = file_get_contents($localPath, false, null, 0, 5);
        if ($head === false || ! str_starts_with($head, '%PDF')) {
            throw ReaderDomainException::validation('File is not a PDF');
        }

        $trimmedTitle = trim($title);
        if ($trimmedTitle === '') {
            throw ReaderDomainException::validation('title is required');
        }

        $resolvedPageCount = $pageCount > 0
            ? $pageCount
            : PdfPageCounter::countFromPath($localPath, $byteSize);

        $id = Uuid::uuid4()->toString();
        $sha256 = hash_file('sha256', $localPath);
        if ($sha256 === false) {
            throw ReaderDomainException::validation('Unable to hash uploaded file');
        }

        $key = SeaweedObjectKey::pdf($userId, $id);
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw ReaderDomainException::validation('Unable to open uploaded file');
        }

        try {
            $this->storage->putStream($key, $stream, ContentType::PDF);
        } finally {
            fclose($stream);
        }

        $now = new DateTimeImmutable('now');
        $document = $this->documents->create(
            $id,
            $userId,
            $trimmedTitle,
            max(0, $resolvedPageCount),
            DocumentStatus::Ready,
        );

        $document = $this->documents->save(new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: max(0, $resolvedPageCount),
            contentType: ContentType::PDF,
            byteSize: $byteSize,
            contentSha256: $sha256,
            seaweedPdfKey: $key,
            seaweedThumbKey: null,
            status: DocumentStatus::Ready,
            isFavorite: $isFavorite,
            revision: $document->revision + 1,
            deletedAt: null,
            lastOpenedAt: null,
            createdAt: $document->createdAt,
            updatedAt: $now,
        ));

        $this->attachCollections($userId, $document->id, $collectionIds);

        return $document;
    }

    /**
     * @param  list<string>  $collectionIds
     */
    private function attachCollections(string $userId, string $documentId, array $collectionIds): void
    {
        $uniqueIds = array_values(array_unique(array_filter(
            $collectionIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        foreach ($uniqueIds as $collectionId) {
            $collection = $this->collections->findByIdForUser($collectionId, $userId);
            if ($collection === null) {
                throw ReaderDomainException::notFound('Collection not found', [
                    'collection_id' => $collectionId,
                ]);
            }

            if ($this->collections->hasDocument($collection->id, $documentId)) {
                continue;
            }

            $this->collections->addDocument($collection->id, $documentId);
            $this->collections->save(new Collection(
                id: $collection->id,
                userId: $collection->userId,
                name: $collection->name,
                revision: $collection->revision + 1,
                documentCount: $collection->documentCount + 1,
                createdAt: $collection->createdAt,
                updatedAt: new DateTimeImmutable('now'),
            ));
        }
    }
}
