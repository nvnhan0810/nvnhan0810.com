<?php

namespace Modules\Todo\Domain;

final class PomodoroStreamVersion
{
    public const CACHE_KEY_PREFIX = 'todo.pomodoro.version.';

    public static function cacheKey(int $userId): string
    {
        return self::CACHE_KEY_PREFIX.$userId;
    }

    public static function current(int $userId): int
    {
        return (int) cache()->get(self::cacheKey($userId), 0);
    }

    public static function bump(int $userId): int
    {
        $next = self::current($userId) + 1;
        cache()->forever(self::cacheKey($userId), $next);

        return $next;
    }
}
