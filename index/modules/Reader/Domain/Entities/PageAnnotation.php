<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

use DateTimeImmutable;
use Modules\Reader\Domain\Enums\AnnotationFormat;

final readonly class PageAnnotation
{
    public function __construct(
        public string $id,
        public string $documentId,
        public string $userId,
        public int $pageIndex,
        public AnnotationFormat $format,
        public ?string $seaweedKey,
        public int $byteSize,
        public ?string $contentSha256,
        public int $revision,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function isEmpty(): bool
    {
        return $this->seaweedKey === null || $this->seaweedKey === '';
    }
}
