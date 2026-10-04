<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

use DateTimeImmutable;
use Modules\Reader\Domain\Enums\DocumentStatus;

final readonly class Document
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $title,
        public int $pageCount,
        public string $contentType,
        public int $byteSize,
        public ?string $contentSha256,
        public ?string $seaweedPdfKey,
        public ?string $seaweedThumbKey,
        public DocumentStatus $status,
        public bool $isFavorite,
        public int $revision,
        public ?DateTimeImmutable $deletedAt,
        public ?DateTimeImmutable $lastOpenedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function hasThumbnail(): bool
    {
        return $this->seaweedThumbKey !== null && $this->seaweedThumbKey !== '';
    }
}
