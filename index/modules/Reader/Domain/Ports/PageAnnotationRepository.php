<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\PageAnnotation;

interface PageAnnotationRepository
{
    public function findByDocumentAndPage(string $documentId, int $pageIndex): ?PageAnnotation;

    /** @return list<PageAnnotation> */
    public function listByDocument(string $documentId): array;

    public function save(PageAnnotation $annotation): PageAnnotation;

    /** @return list<PageAnnotation> */
    public function changedSince(string $userId, DateTimeImmutable $since): array;
}
