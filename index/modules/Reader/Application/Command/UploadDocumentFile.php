<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Command;

use Modules\Shared\Application\Command;

final class UploadDocumentFile implements Command
{
    /** @param  resource  $stream */
    public function __construct(
        public readonly string $userId,
        public readonly string $documentId,
        public readonly mixed $stream,
        public readonly int $byteSize,
        public readonly int $pageCount,
        public readonly ?string $contentSha256,
    ) {}
}
