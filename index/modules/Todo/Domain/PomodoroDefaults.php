<?php

namespace Modules\Todo\Domain;

final class PomodoroDefaults
{
    public const FOCUS_MINUTES = 20;

    public const SHORT_BREAK_MINUTES = 5;

    public const SESSIONS_BEFORE_LONG_BREAK = 4;

    public const LONG_BREAK_MINUTES = 15;

    public const PHASE_FOCUS = 'focus';

    public const PHASE_SHORT_BREAK = 'short_break';

    public const PHASE_LONG_BREAK = 'long_break';

    /** @var list<string> */
    public const PHASES = [
        self::PHASE_FOCUS,
        self::PHASE_SHORT_BREAK,
        self::PHASE_LONG_BREAK,
    ];

    public static function remainingMsForFocus(): int
    {
        return self::FOCUS_MINUTES * 60_000;
    }
}
