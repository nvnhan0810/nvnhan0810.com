<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Infrastructure\Enrichment\RankingService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestSettingsModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SubjectModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserInterestScoreModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\RetrievalService;
use App\Domains\ReadingDigest\Presentation\Jobs\RunDailyDigestJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $settings = DigestSettingsModel::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'notification_time' => config('reading-digest.notification_time', '08:00'),
                'timezone' => config('reading-digest.timezone', 'Asia/Ho_Chi_Minh'),
            ]
        );

        $recentRuns = DigestRunModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('run_date')
            ->limit(30)
            ->get();

        return Inertia::render('domains/reading-digest/pages/admin/settings/SettingsPage', [
            'settings' => $settings,
            'recentRuns' => $recentRuns,
        ]);
    }

    public function update(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'notification_time' => 'required|string',
            'timezone' => 'required|string',
        ]);

        DigestSettingsModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'notification_time' => $data['notification_time'],
                'timezone' => $data['timezone'],
            ]
        );

        return back()->with('success', 'Đã lưu cài đặt.');
    }

    public function resetLearning(Request $request)
    {
        $userId = $request->user()->id;

        UserInterestScoreModel::query()
            ->where('user_id', $userId)
            ->delete();

        UserReadingProfileModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'preferences' => DefaultPreferences::make(),
                'user_embedding' => null,
                'embedding_updated_at' => null,
            ]
        );

        return back()->with('success', 'Đã reset sở thích học từ vote.');
    }

    public function sendNow(Request $request)
    {
        RunDailyDigestJob::dispatch();

        return back()->with('success', 'Fetch and digest queued — check Today in a minute.');
    }

    public function preview(Request $request, RetrievalService $retrievalService, RankingService $rankingService)
    {
        $userId = $request->user()->id;
        $profile = UserReadingProfileModel::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preview = [];
        $subjects = SubjectModel::query()->where('enabled', true)->with('sources')->get();

        foreach ($subjects as $subject) {
            if ($subject->sources->isEmpty()) {
                continue;
            }

            $candidates = $retrievalService->retrieveForSubject($subject, $userId, 10);
            $rankings = $rankingService->rank(
                $candidates,
                $profile->preferences ?? DefaultPreferences::make(),
                min(5, $subject->articles_per_digest ?? 5)
            );

            $preview[] = [
                'subject' => $subject->only(['id', 'name']),
                'items' => collect($rankings)->map(function ($ranking) use ($candidates) {
                    $candidate = collect($candidates)->first(
                        fn ($c) => $c['article']->id === $ranking['article_id']
                    );

                    return [
                        'article' => $candidate['article']->only(['id', 'title', 'url']),
                        'retrieval_score' => $candidate['score'] ?? null,
                        'llm_score' => $ranking['score'] ?? null,
                        'reason' => $ranking['reason'] ?? null,
                    ];
                }),
            ];
        }

        return response()->json(['preview' => $preview]);
    }
}
