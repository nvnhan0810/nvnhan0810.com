<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Service;

/** Keeps HTTP validation in sync with config('reader.max_pdf_bytes'). */
final class ReaderUploadLimits
{
    public static function maxBytes(): int
    {
        return max(1, (int) config('reader.max_pdf_bytes', 200 * 1024 * 1024));
    }

    /** Laravel `max` rule for uploaded files is in kilobytes. */
    public static function maxKilobytes(): int
    {
        return (int) ceil(self::maxBytes() / 1024);
    }

    public static function maxMegabytesLabel(): int
    {
        return (int) floor(self::maxBytes() / (1024 * 1024));
    }
}
