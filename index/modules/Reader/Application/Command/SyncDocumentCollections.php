<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class SyncDocumentCollections implements Command
{
    /**
     * @param  list<string>  $collectionIds
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $documentId,
        public readonly array $collectionIds,
    ) {}
}
