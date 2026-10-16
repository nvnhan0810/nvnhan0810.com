<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\PageAnnotation;
use Modules\Reader\Domain\Enums\AnnotationFormat;

interface PageAnnotationRepository
{
    public function findByDocumentPageAndFormat(
        string $documentId,
        int $pageIndex,
        AnnotationFormat $format,
    ): ?PageAnnotation;

    /** @return list<PageAnnotation> */
    public function listByDocument(string $documentId): array;

    /** @return list<PageAnnotation> */
    public function listByDocumentAndFormat(string $documentId, AnnotationFormat $format): array;

    public function save(PageAnnotation $annotation): PageAnnotation;

    /** @return list<PageAnnotation> */
    public function changedSince(string $userId, DateTimeImmutable $since): array;
}
