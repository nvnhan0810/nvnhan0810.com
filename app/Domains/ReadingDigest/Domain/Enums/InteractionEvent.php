<?php

namespace App\Domains\ReadingDigest\Domain\Enums;

enum InteractionEvent: string
{
    case Impression = 'impression';
    case Opened = 'opened';
    case FinishedReading = 'finished_reading';
    case Saved = 'saved';
    case Liked = 'liked';
    case Disliked = 'disliked';
    case Shared = 'shared';
    case Dismissed = 'dismissed';
    case Rated = 'rated';
    case RatedPositive = 'rated_positive';
    case RatedNegative = 'rated_negative';
}
