<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\ReadingProgress;

interface ReadingProgressRepository
{
    public function findByDocument(string $documentId, string $userId): ?ReadingProgress;

    public function save(ReadingProgress $progress): ReadingProgress;

    /** @return list<ReadingProgress> */
    public function changedSince(string $userId, DateTimeImmutable $since): array;
}
