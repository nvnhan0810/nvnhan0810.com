<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Service;

use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Throwable;

/**
 * Permanently remove a document: Seaweed objects + DB row (cascades progress/annotations).
 */
final class DocumentHardDeleter
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly PageAnnotationRepository $annotations,
        private readonly ObjectStorage $storage,
    ) {}

    public function purge(Document $document): void
    {
        // Best-effort S3 cleanup — never fail the DB hard-delete.
        $this->deleteObject($document->seaweedPdfKey);
        $this->deleteObject($document->seaweedThumbKey);

        try {
            foreach ($this->annotations->listByDocument($document->id) as $annotation) {
                $this->deleteObject($annotation->seaweedKey);
            }
        } catch (Throwable) {
            // Continue to hard-delete even if annotation listing/storage fails.
        }

        $this->documents->hardDelete($document->id);
    }

    private function deleteObject(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        try {
            $this->storage->delete($key);
        } catch (Throwable) {
            // Missing object is fine during purge.
        }
    }
}
