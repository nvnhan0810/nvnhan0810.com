<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Storage;

use Aws\S3\S3Client;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Reader\Domain\Ports\ObjectStorage;
use RuntimeException;
use Throwable;

final class SeaweedObjectStorage implements ObjectStorage
{
    private function diskName(): string
    {
        return (string) config('reader.disk', 's3');
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * @return array<string, mixed>
     */
    private function diskConfig(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('filesystems.disks.'.$this->diskName(), []);

        return $config;
    }

    /**
     * Browser-facing S3 base (public ingress). Null → sign with disk endpoint.
     */
    private function publicEndpoint(): ?string
    {
        $config = $this->diskConfig();
        $candidates = [
            $config['temporary_url'] ?? null,
            $config['url'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = rtrim($candidate, '/');
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
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
            $publicEndpoint = $this->publicEndpoint();
            if ($publicEndpoint === null) {
                return $this->disk()->temporaryUrl($key, $expires);
            }

            // Sign against the public host so SigV4 Host matches what browsers call
            // (AWS_ENDPOINT may be in-cluster *.svc.cluster.local).
            $config = $this->diskConfig();
            $bucket = $config['bucket'] ?? null;
            $region = $config['region'] ?? null;
            $keyId = $config['key'] ?? null;
            $secret = $config['secret'] ?? null;

            if (! is_string($bucket) || $bucket === ''
                || ! is_string($region) || $region === ''
                || ! is_string($keyId) || $keyId === ''
                || ! is_string($secret) || $secret === '') {
                return $this->disk()->temporaryUrl($key, $expires);
            }

            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => $publicEndpoint,
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
                'credentials' => [
                    'key' => $keyId,
                    'secret' => $secret,
                ],
            ]);

            $command = $client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            return (string) $client->createPresignedRequest($command, $expires)->getUri();
        } catch (Throwable) {
            return null;
        }
    }
}
