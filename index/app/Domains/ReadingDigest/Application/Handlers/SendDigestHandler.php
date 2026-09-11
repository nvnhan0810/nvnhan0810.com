<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Telegram\TelegramDigestNotifier;

class SendDigestHandler
{
    public function __construct(
        private readonly TelegramDigestNotifier $notifier,
    ) {}

    public function handle(string $digestRunId): void
    {
        $run = DigestRunModel::query()->findOrFail($digestRunId);

        if ($this->notifier->send($run)) {
            $run->update(['telegram_sent_at' => now()]);
        }
    }
}
