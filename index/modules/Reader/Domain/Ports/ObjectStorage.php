<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use DateTimeInterface;

interface ObjectStorage
{
    public function put(string $key, string $contents, string $contentType): void;

    /** @param  resource  $stream */
    public function putStream(string $key, $stream, string $contentType): void;

    public function get(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    public function temporaryUrl(string $key, DateTimeInterface $expires): ?string;
}
