<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdDigestRun;
use Modules\ReadingDigest\Infrastructure\Telegram\TelegramDigestNotifier;

class SendDigestHandler
{
    public function __construct(
        private readonly TelegramDigestNotifier $notifier,
    ) {}

    public function handle(string $digestRunId): void
    {
        $run = RdDigestRun::query()->findOrFail($digestRunId);

        if ($this->notifier->send($run)) {
            $run->update(['telegram_sent_at' => now()]);
        }
    }
}
