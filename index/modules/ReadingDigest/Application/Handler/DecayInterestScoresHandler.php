<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdUserInterestScore;
use Modules\ReadingDigest\Domain\Services\InterestScoreService;

class DecayInterestScoresHandler
{
    public function __construct(
        private readonly InterestScoreService $interestScoreService,
    ) {}

    public function handle(): int
    {
        $factor = (float) config('reading-digest.interest_decay_factor', 0.98);
        $updated = 0;

        RdUserInterestScore::query()->orderBy('id')->chunk(100, function ($scores) use ($factor, &$updated) {
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
