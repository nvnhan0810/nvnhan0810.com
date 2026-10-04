<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

use DateTimeImmutable;

final readonly class Collection
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $name,
        public int $revision,
        public int $documentCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
