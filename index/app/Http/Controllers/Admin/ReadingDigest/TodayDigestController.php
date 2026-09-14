<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Http\Controllers\Controller;
use App\Models\RdDigestRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayDigestController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $run = RdDigestRun::query()
            ->where('user_id', $userId)
            ->whereDate('run_date', $today)
            ->with(['items.article.source', 'items.subject'])
            ->first();

        return Inertia::render('domains/reading-digest/pages/admin/TodayPage', [
            'run' => $run,
        ]);
    }
}
