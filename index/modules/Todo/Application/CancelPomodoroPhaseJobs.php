<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort cancel of pending delayed SendPomodoroPhasePushJob rows (database queue).
 * Deliver still guards by session_uuid if a row races past cancel.
 */
final class CancelPomodoroPhaseJobs
{
    public function execute(?string $sessionUuid): void
    {
        if ($sessionUuid === null || $sessionUuid === '') {
            return;
        }

        try {
            $deleted = DB::table('jobs')
                ->where('payload', 'like', '%'.$sessionUuid.'%')
                ->delete();

            if ($deleted > 0) {
                Log::info('web-push.pomodoro.jobs_cancelled', [
                    'session_uuid' => $sessionUuid,
                    'deleted' => $deleted,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('web-push.pomodoro.jobs_cancel_failed', [
                'session_uuid' => $sessionUuid,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
