<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Enrichment\RankingService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SubjectModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\RetrievalService;
use App\Domains\ReadingDigest\Presentation\Jobs\SendDigestTelegramJob;
use Illuminate\Support\Str;

class RunDailyDigestHandler
{
    public function __construct(
        private readonly RetrievalService $retrievalService,
        private readonly RankingService $rankingService,
    ) {}

    public function handle(int $userId, ?\DateTimeInterface $runDate = null): DigestRunModel
    {
        $runDate ??= now();
        $limit = (int) config('reading-digest.retrieval_candidates', 30);

        $profile = UserReadingProfileModel::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $run = DigestRunModel::create([
            'user_id' => $userId,
            'run_date' => $runDate,
            'status' => 'running',
            'stats' => [],
        ]);

        $subjects = SubjectModel::query()->where('enabled', true)->with('sources')->get();
        $totalItems = 0;

        foreach ($subjects as $subject) {
            if ($subject->sources->isEmpty()) {
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

                DigestRunItemModel::create([
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
            ],
        ]);

        SendDigestTelegramJob::dispatch($run->id);

        return $run;
    }
}
