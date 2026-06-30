<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\FetchAllSourcesHandler;
use App\Domains\ReadingDigest\Application\Handlers\RunDailyDigestHandler;
use App\Domains\ReadingDigest\Application\Handlers\SendDigestHandler;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDailyDigestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(
        FetchAllSourcesHandler $fetchAllSources,
        RunDailyDigestHandler $digestHandler,
        SendDigestHandler $sendDigest,
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

        $sendDigest->handle($run->id);
    }
}
