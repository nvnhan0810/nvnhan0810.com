<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserInterestScoreModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $profile = UserReadingProfileModel::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $interestScores = UserInterestScoreModel::query()
            ->where('user_id', $userId)
            ->with('taxonomyNode')
            ->orderByDesc('score')
            ->limit(50)
            ->get();

        return Inertia::render('domains/reading-digest/pages/admin/profile/ProfilePage', [
            'profile' => $profile,
            'interestScores' => $interestScores,
        ]);
    }

    public function reset(Request $request)
    {
        $userId = $request->user()->id;

        UserInterestScoreModel::query()->where('user_id', $userId)->delete();
        UserReadingProfileModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'preferences' => DefaultPreferences::make(),
                'user_embedding' => null,
                'embedding_updated_at' => null,
            ]
        );

        return back()->with('success', 'Profile reset.');
    }
}
