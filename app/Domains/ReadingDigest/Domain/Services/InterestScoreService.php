<?php

namespace App\Domains\ReadingDigest\Domain\Services;

use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;

class InterestScoreService
{
    public function weightForEvent(InteractionEvent $event, ?array $metadata = null): float
    {
        $weights = config('reading-digest.interaction_weights', []);

        if ($event === InteractionEvent::Rated && is_array($metadata)) {
            $sentiment = $metadata['sentiment'] ?? null;
            if ($sentiment === 'positive') {
                return (float) ($weights['rated_positive'] ?? 4);
            }
            if ($sentiment === 'negative') {
                return (float) ($weights['rated_negative'] ?? -6);
            }
        }

        return (float) ($weights[$event->value] ?? 0);
    }

    public function applyDecay(float $score, ?float $factor = null): float
    {
        $factor ??= (float) config('reading-digest.interest_decay_factor', 0.98);

        return round($score * $factor, 4);
    }
}
