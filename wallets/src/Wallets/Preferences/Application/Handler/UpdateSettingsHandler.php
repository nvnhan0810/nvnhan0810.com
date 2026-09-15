<?php

namespace Wallets\Preferences\Application\Handler;

use App\Models\Setting;
use Wallets\Preferences\Application\Command\UpdateSettings;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class UpdateSettingsHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof UpdateSettings);
        Setting::setForUser($command->userId, 'recurring_alert_days', $command->recurringAlertDays);
        Setting::setForUser($command->userId, 'telegram_chat_id', $command->telegramChatId ?? '');
        Setting::setForUser($command->userId, 'telegram_enabled', $command->telegramEnabled ? '1' : '');

        return null;
    }
}
