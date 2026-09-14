<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdDigestRun;
use App\Models\RdDigestRunItem;
use App\Models\RdSubject;
use App\Models\RdUserReadingProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\ReadingDigest\Infrastructure\Enrichment\RankingService;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\RetrievalService;

class RunDailyDigestHandler
{
    public function __construct(
        private readonly RetrievalService $retrievalService,
        private readonly RankingService $rankingService,
    ) {}

    public function handle(int $userId, ?\DateTimeInterface $runDate = null): RdDigestRun
    {
        $runDate ??= now();
        $limit = (int) config('reading-digest.retrieval_candidates', 30);

        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $run = RdDigestRun::create([
            'user_id' => $userId,
            'run_date' => $runDate,
            'status' => 'running',
            'stats' => [],
        ]);

        $subjects = RdSubject::query()->where('enabled', true)->with('sources')->get();
        $totalItems = 0;

        foreach ($subjects as $subject) {
            if ($subject->sources->isEmpty()) {
                Log::warning('Reading digest subject has no linked sources; skipping', [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                ]);

                continue;
            }

            $candidates = $this->retrievalService->retrieveForSubject($subject, $userId, $limit);
            $articlesPerDigest = $subject->articles_per_digest
                ?? config('reading-digest.articles_per_subject', 5);

            $rankings = $this->rankingService->rank(
                $candidates,
                $profile->preferences ?? DefaultPreferences::make(),
                $articlesPerDigest
            );

            $rank = 1;
            foreach ($rankings as $ranking) {
                $candidate = collect($candidates)->first(
                    fn ($c) => $c['article']->id === $ranking['article_id']
                );
                $retrievalScore = $candidate['score'] ?? null;

                RdDigestRunItem::create([
                    'digest_run_id' => $run->id,
                    'subject_id' => $subject->id,
                    'article_id' => $ranking['article_id'],
                    'rank' => $rank++,
                    'retrieval_score' => $retrievalScore,
                    'llm_score' => $ranking['score'] ?? null,
                    'llm_reason' => $ranking['reason'] ?? null,
                    'tracking_token' => Str::random(32),
                ]);
                $totalItems++;
            }
        }

        $run->update([
            'status' => 'completed',
            'stats' => [
                'subjects' => $subjects->count(),
                'items' => $totalItems,
                'subjects_with_sources' => $subjects->filter(fn ($s) => $s->sources->isNotEmpty())->count(),
            ],
        ]);

        return $run;
    }
}
