<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\ValueObjects;

final class SeaweedObjectKey
{
    private const PREFIX = 'reader';

    public static function pdf(string $userId, string $documentId): string
    {
        return sprintf('%s/users/%s/documents/%s/original.pdf', self::PREFIX, $userId, $documentId);
    }

    public static function thumbnail(string $userId, string $documentId): string
    {
        return sprintf('%s/users/%s/documents/%s/thumb.jpg', self::PREFIX, $userId, $documentId);
    }

    public static function drawing(string $userId, string $documentId, int $pageIndex): string
    {
        return sprintf(
            '%s/users/%s/documents/%s/drawings/page-%d.drawing',
            self::PREFIX,
            $userId,
            $documentId,
            $pageIndex,
        );
    }
}
