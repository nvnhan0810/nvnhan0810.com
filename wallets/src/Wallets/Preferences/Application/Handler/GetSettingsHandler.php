<?php

namespace Wallets\Preferences\Application\Handler;

use App\Models\Setting;
use Wallets\Preferences\Application\Query\GetSettings;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class GetSettingsHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof GetSettings);

        return [
            'recurring_alert_days' => Setting::recurringAlertDays($query->userId),
            'telegram_chat_id' => Setting::telegramChatId($query->userId),
            'telegram_enabled' => Setting::telegramEnabled($query->userId),
        ];
    }
}
