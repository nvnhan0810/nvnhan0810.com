<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class PutPageAnnotation implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $documentId,
        public readonly int $pageIndex,
        public readonly bool $empty,
        public readonly ?string $binary,
        public readonly ?int $baseRevision,
        public readonly ?string $contentSha256,
    ) {}
}
