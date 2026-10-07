<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

use DateTimeImmutable;

final readonly class ReadingProgress
{
    public function __construct(
        public string $documentId,
        public string $userId,
        public int $pageIndex,
        public int $revision,
        public DateTimeImmutable $updatedAt,
    ) {}
}
