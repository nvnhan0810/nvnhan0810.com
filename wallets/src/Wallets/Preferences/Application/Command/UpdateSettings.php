<?php

namespace Wallets\Preferences\Application\Command;

use Wallets\Shared\Application\Command;

final class UpdateSettings implements Command
{
    public function __construct(
        public readonly int $userId,
        public readonly int $recurringAlertDays,
        public readonly ?string $telegramChatId = null,
        public readonly bool $telegramEnabled = false,
    ) {}
}
