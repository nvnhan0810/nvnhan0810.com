<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class ImportDocumentFromUrl implements Command
{
    /**
     * @param  list<string>  $collectionIds
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly string $url,
        public readonly int $pageCount,
        public readonly array $collectionIds,
        public readonly bool $isFavorite,
    ) {}
}
