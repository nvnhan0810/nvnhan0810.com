<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\ValueObjects;

final readonly class FetchedPdf
{
    public function __construct(
        public string $contents,
        public int $byteSize,
        public ?string $suggestedTitle,
    ) {}
}
