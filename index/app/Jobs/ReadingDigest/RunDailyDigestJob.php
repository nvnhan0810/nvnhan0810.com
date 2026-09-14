<?php

namespace App\Jobs\ReadingDigest;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\FetchAllSourcesHandler;
use Modules\ReadingDigest\Application\Handler\RunDailyDigestHandler;

class RunDailyDigestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(
        FetchAllSourcesHandler $fetchAllSources,
        RunDailyDigestHandler $digestHandler,
    ): void {
        $fetchStats = $fetchAllSources->handle();

        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            return;
        }

        $run = $digestHandler->handle($user->id);

        $run->update([
            'stats' => array_merge($run->stats ?? [], [
                'fetch' => $fetchStats,
            ]),
        ]);

        SendDigestTelegramJob::dispatch($run->id);
    }
}
