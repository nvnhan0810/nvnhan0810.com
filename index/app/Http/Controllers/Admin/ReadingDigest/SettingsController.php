<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Http\Controllers\Controller;
use App\Jobs\ReadingDigest\RunDailyDigestJob;
use App\Models\RdDigestRun;
use App\Models\RdDigestSettings;
use App\Models\RdSubject;
use App\Models\RdUserInterestScore;
use App\Models\RdUserReadingProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Modules\ReadingDigest\Infrastructure\Enrichment\RankingService;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\RetrievalService;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $settings = RdDigestSettings::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'notification_time' => config('reading-digest.notification_time', '08:00'),
                'timezone' => config('reading-digest.timezone', 'Asia/Ho_Chi_Minh'),
            ]
        );

        $recentRuns = RdDigestRun::query()
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

        RdDigestSettings::query()->updateOrCreate(
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

        RdUserInterestScore::query()
            ->where('user_id', $userId)
            ->delete();

        RdUserReadingProfile::query()->updateOrCreate(
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
        Bus::dispatch(new RunDailyDigestJob);

        return back()->with('success', 'Fetch and digest queued — check Today in a minute.');
    }

    public function preview(Request $request, RetrievalService $retrievalService, RankingService $rankingService)
    {
        $userId = $request->user()->id;
        $profile = RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preview = [];
        $subjects = RdSubject::query()->where('enabled', true)->with('sources')->get();

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
