<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Storage;

use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Reader\Domain\Ports\ObjectStorage;
use RuntimeException;
use Throwable;

final class SeaweedObjectStorage implements ObjectStorage
{
    private function disk(): Filesystem
    {
        return Storage::disk((string) config('reader.disk', 's3'));
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        $ok = $this->disk()->put($key, $contents, [
            'ContentType' => $contentType,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('Failed to store object: '.$key);
        }
    }

    public function putStream(string $key, $stream, string $contentType): void
    {
        $ok = $this->disk()->writeStream($key, $stream, [
            'ContentType' => $contentType,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('Failed to stream object: '.$key);
        }
    }

    public function get(string $key): string
    {
        $contents = $this->disk()->get($key);
        if ($contents === null) {
            throw new RuntimeException('Object not found: '.$key);
        }

        return $contents;
    }

    public function delete(string $key): void
    {
        try {
            if (! $this->disk()->exists($key)) {
                return;
            }

            $this->disk()->delete($key);
        } catch (Throwable) {
            // Missing / unreachable object must not block purge.
        }
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($key);
    }

    public function temporaryUrl(string $key, DateTimeInterface $expires): ?string
    {
        try {
            return $this->disk()->temporaryUrl($key, $expires);
        } catch (Throwable) {
            return null;
        }
    }
}
