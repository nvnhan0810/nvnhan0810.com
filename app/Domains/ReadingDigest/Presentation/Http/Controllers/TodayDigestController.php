<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayDigestController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $run = DigestRunModel::query()
            ->where('user_id', $userId)
            ->whereDate('run_date', $today)
            ->with(['items.article.source', 'items.subject'])
            ->first();

        return Inertia::render('domains/reading-digest/pages/admin/TodayPage', [
            'run' => $run,
        ]);
    }
}
