<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Service;

/**
 * Best-effort page count from PDF binary (no external library).
 */
final class PdfPageCounter
{
    public static function count(string $contents): int
    {
        if ($contents === '' || ! str_starts_with($contents, '%PDF')) {
            return 0;
        }

        if (preg_match_all('/\/Type\s*\/Page(?![sA-Za-z])/', $contents, $matches) === false) {
            return 0;
        }

        return max(0, count($matches[0]));
    }

    /** Skip full-file scan above 100MB to avoid memory spikes on huge PDFs. */
    public static function countFromPath(string $path, int $byteSize): int
    {
        $scanLimit = 100 * 1024 * 1024;
        if ($byteSize <= 0 || $byteSize > $scanLimit) {
            return 0;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return 0;
        }

        return self::count($contents);
    }
}
