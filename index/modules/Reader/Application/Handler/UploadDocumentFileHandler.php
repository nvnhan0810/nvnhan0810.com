<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\UploadDocumentFile;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\ContentType;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\ValueObjects\SeaweedObjectKey;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class UploadDocumentFileHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ObjectStorage $storage,
    ) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof UploadDocumentFile);

        $maxBytes = (int) config('reader.max_pdf_bytes');
        if ($command->byteSize > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('PDF exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $contents = stream_get_contents($command->stream);
        if ($contents === false) {
            throw ReaderDomainException::validation('Unable to read uploaded file');
        }

        $sha256 = $command->contentSha256 ?? hash('sha256', $contents);
        if ($command->contentSha256 !== null && ! hash_equals($command->contentSha256, hash('sha256', $contents))) {
            throw ReaderDomainException::validation('content_sha256 mismatch');
        }

        // Basic PDF magic check
        if (! str_starts_with($contents, '%PDF')) {
            throw ReaderDomainException::validation('File is not a PDF');
        }

        $key = SeaweedObjectKey::pdf($command->userId, $command->documentId);
        $this->storage->put($key, $contents, ContentType::PDF);

        $now = new DateTimeImmutable('now');
        $updated = new Document(
            id: $document->id,
            userId: $document->userId,
            title: $document->title,
            pageCount: max(0, $command->pageCount),
            contentType: ContentType::PDF,
            byteSize: strlen($contents),
            contentSha256: $sha256,
            seaweedPdfKey: $key,
            seaweedThumbKey: $document->seaweedThumbKey,
            status: DocumentStatus::Ready,
            isFavorite: $document->isFavorite,
            revision: $document->revision + 1,
            deletedAt: null,
            lastOpenedAt: $document->lastOpenedAt,
            createdAt: $document->createdAt,
            updatedAt: $now,
        );

        return $this->documents->save($updated);
    }
}
