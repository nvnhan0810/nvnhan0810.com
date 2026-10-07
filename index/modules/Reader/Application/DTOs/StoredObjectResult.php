<?php

declare(strict_types=1);

namespace Modules\Reader\Application\DTOs;

final readonly class StoredObjectResult
{
    public function __construct(
        public ?string $temporaryUrl,
        public ?string $binary,
        public string $contentType,
        public ?int $revision = null,
        public ?string $contentSha256 = null,
    ) {}
}
