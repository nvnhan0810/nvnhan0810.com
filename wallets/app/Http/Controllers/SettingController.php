<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Wallets\Preferences\Application\Command\UpdateSettings;
use Wallets\Preferences\Application\Query\GetSettings;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\QueryBus;

class SettingController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index()
    {
        $settings = $this->queries->ask(new GetSettings(userId: auth()->id()));
        $recurringAlertDays = $settings['recurring_alert_days'];
        $telegramChatId = $settings['telegram_chat_id'];
        $telegramEnabled = $settings['telegram_enabled'];

        return Inertia::render('Settings/Index', compact('recurringAlertDays', 'telegramChatId', 'telegramEnabled'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'recurring_alert_days' => 'required|integer|min:1|max:30',
            'telegram_chat_id' => 'nullable|string|max:64',
            'telegram_enabled' => 'sometimes|boolean',
        ]);

        $this->commands->dispatch(new UpdateSettings(
            userId: auth()->id(),
            recurringAlertDays: (int) $validated['recurring_alert_days'],
            telegramChatId: $validated['telegram_chat_id'] ?? null,
            telegramEnabled: $request->boolean('telegram_enabled'),
        ));

        return redirect()->route('settings.index')->with('success', 'Đã lưu cài đặt.');
    }
}
