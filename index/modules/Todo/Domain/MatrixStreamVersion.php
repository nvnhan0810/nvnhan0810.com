<?php

namespace Modules\Todo\Domain;

final class MatrixStreamVersion
{
    public const CACHE_KEY = 'todo.matrix.version';

    public static function current(): int
    {
        return (int) cache()->get(self::CACHE_KEY, 0);
    }

    public static function bump(): void
    {
        $next = self::current() + 1;
        cache()->forever(self::CACHE_KEY, $next);
    }
}
