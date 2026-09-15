<?php

namespace Wallets\Reporting\Domain;

final class FixedExpenseGranularity
{
    public const WEEK = 'week';

    public const MONTH = 'month';

    public const YEAR = 'year';

    public const CUSTOM = 'custom';

    /** @var list<string> */
    public const ALL = [self::WEEK, self::MONTH, self::YEAR, self::CUSTOM];

    public const DEFAULT_DURATION = 5;

    public const MIN_DURATION = 1;

    public const MAX_DURATION = 24;

    public const DEFAULT_CUSTOM_DAYS = 30;

    public const MIN_CUSTOM_DAYS = 1;

    public const MAX_CUSTOM_DAYS = 366;

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function clampDuration(int $duration): int
    {
        return max(self::MIN_DURATION, min(self::MAX_DURATION, $duration));
    }

    public static function clampCustomDays(int $days): int
    {
        return max(self::MIN_CUSTOM_DAYS, min(self::MAX_CUSTOM_DAYS, $days));
    }
}
