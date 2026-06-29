<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\RunDailyDigestHandler;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDailyDigestJob implements ShouldQueue
{
    use Queueable;

    public function handle(RunDailyDigestHandler $handler): void
    {
        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            return;
        }

        $handler->handle($user->id);
    }
}
