<?php

namespace App\Jobs\Todo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Todo\Application\DeliverPomodoroPhasePush;

final class SendPomodoroPhasePushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $userId,
        public readonly int $expectedEndsAtMs,
    ) {}

    public function handle(DeliverPomodoroPhasePush $deliver): void
    {
        $deliver->execute($this->userId, $this->expectedEndsAtMs);
    }
}
