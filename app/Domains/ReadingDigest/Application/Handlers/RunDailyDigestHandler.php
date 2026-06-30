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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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

        Log::warning('[ReadingDigest] RunDailyDigestHandler: started', [
            'user_id' => $userId,
            'run_date' => $runDate->format('Y-m-d'),
            'retrieval_candidates_limit' => $limit,
        ]);

        try {
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

            Log::warning('[ReadingDigest] RunDailyDigestHandler: digest run created', [
                'digest_run_id' => $run->id,
            ]);

            $subjects = SubjectModel::query()->where('enabled', true)->with('sources')->get();
            $totalItems = 0;

            Log::warning('[ReadingDigest] RunDailyDigestHandler: processing subjects', [
                'digest_run_id' => $run->id,
                'subjects_count' => $subjects->count(),
            ]);

            foreach ($subjects as $subject) {
                if ($subject->sources->isEmpty()) {
                    Log::warning('[ReadingDigest] RunDailyDigestHandler: subject has no linked sources; skipping', [
                        'digest_run_id' => $run->id,
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                    ]);

                    continue;
                }

                $candidates = $this->retrievalService->retrieveForSubject($subject, $userId, $limit);
                $articlesPerDigest = $subject->articles_per_digest
                    ?? config('reading-digest.articles_per_subject', 5);

                Log::warning('[ReadingDigest] RunDailyDigestHandler: retrieved candidates', [
                    'digest_run_id' => $run->id,
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'candidates_count' => count($candidates),
                    'articles_per_digest' => $articlesPerDigest,
                ]);

                $rankings = $this->rankingService->rank(
                    $candidates,
                    $profile->preferences ?? DefaultPreferences::make(),
                    $articlesPerDigest
                );

                Log::warning('[ReadingDigest] RunDailyDigestHandler: ranking completed', [
                    'digest_run_id' => $run->id,
                    'subject_id' => $subject->id,
                    'rankings_count' => count($rankings),
                ]);

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
                    'subjects_with_sources' => $subjects->filter(fn ($s) => $s->sources->isNotEmpty())->count(),
                ],
            ]);

            Log::warning('[ReadingDigest] RunDailyDigestHandler: digest completed, dispatching Telegram job', [
                'digest_run_id' => $run->id,
                'total_items' => $totalItems,
                'stats' => $run->stats,
                'queue_connection' => config('queue.default'),
            ]);

            SendDigestTelegramJob::dispatch($run->id);

            Log::warning('[ReadingDigest] RunDailyDigestHandler: SendDigestTelegramJob dispatched', [
                'digest_run_id' => $run->id,
            ]);

            return $run;
        } catch (Throwable $e) {
            Log::warning('[ReadingDigest] RunDailyDigestHandler: failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
