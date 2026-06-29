<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Services\InterestScoreService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserInterestScoreModel;

class DecayInterestScoresHandler
{
    public function __construct(
        private readonly InterestScoreService $interestScoreService,
    ) {}

    public function handle(): int
    {
        $factor = (float) config('reading-digest.interest_decay_factor', 0.98);
        $updated = 0;

        UserInterestScoreModel::query()->orderBy('id')->chunk(100, function ($scores) use ($factor, &$updated) {
            foreach ($scores as $score) {
                $score->update([
                    'score' => $this->interestScoreService->applyDecay($score->score, $factor),
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        });

        return $updated;
    }
}
