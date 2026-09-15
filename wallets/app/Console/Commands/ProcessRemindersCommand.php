<?php

namespace App\Console\Commands;

use App\Models\RecurringItem;
use App\Models\RecurringOccurrence;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceGenerator;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceStateService;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'finance:process-reminders';

    protected $description = 'Cập nhật trạng thái kỳ (vay + thu/chi cố định) và gửi Telegram nhắc kỳ tới hạn';

    public function handle(
        RecurringOccurrenceGenerator $generator,
        RecurringOccurrenceStateService $state,
        TelegramNotifier $telegram,
    ): int {
        $this->call('loans:process-periods');

        $this->syncOccurrences($generator);
        $state->transitionStatuses();

        if (! $telegram->isConfigured()) {
            $this->warn('Chưa cấu hình TELEGRAM_BOT_TOKEN — bỏ qua nhắc thu/chi.');

            return self::SUCCESS;
        }

        $sent = $this->remindRecurring($state, $telegram);
        $this->info("Đã gửi {$sent} nhắc Telegram cho thu/chi cố định.");

        return self::SUCCESS;
    }

    private function syncOccurrences(RecurringOccurrenceGenerator $generator): void
    {
        RecurringItem::query()
            ->where('is_active', true)
            ->get()
            ->each(function (RecurringItem $item) use ($generator) {
                $horizon = Carbon::today()->addDays(Setting::recurringAlertDays($item->user_id));
                $generator->ensure($item, $horizon);
            });
    }

    private function remindRecurring(RecurringOccurrenceStateService $state, TelegramNotifier $telegram): int
    {
        $userIds = RecurringOccurrence::query()
            ->whereIn('status', [RecurringOccurrence::STATUS_DUE, RecurringOccurrence::STATUS_OVERDUE])
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $sent = 0;

        foreach ($userIds as $userId) {
            if (! Setting::telegramEnabled($userId)) {
                continue;
            }

            $chatId = Setting::telegramChatId($userId);
            if (! $chatId) {
                continue;
            }

            foreach ($state->dueTodayUnremindedOccurrences($userId) as $occ) {
                $item = $occ->recurringItem;
                if (! $item) {
                    continue;
                }

                $payUrl = URL::route('transactions.create', array_filter([
                    'type' => $item->type,
                    'wallet_id' => $item->wallet_id,
                    'amount' => (int) $occ->expected_amount,
                    'description' => $item->name,
                    'transacted_at' => $occ->due_date->format('d/m/Y'),
                    'recurring_item_id' => $item->id,
                    'recurring_occurrence_id' => $occ->id,
                ]));

                $overdue = $occ->due_date->isPast() && ! $occ->due_date->isToday();
                $label = $item->type === 'income' ? 'khoản thu' : 'khoản chi';
                $heading = $overdue
                    ? '⚠️ <b>Quá hạn '.$label.' cố định</b>'
                    : '🔔 <b>Đến hạn '.$label.' cố định</b>';

                $text = $heading."\n\n"
                    .'Khoản: <b>'.e($item->name)."</b>\n"
                    .'Số tiền: <b>'.number_format((float) $occ->expected_amount, 0).' ₫</b>'."\n"
                    .'Ngày đến hạn: <b>'.$occ->due_date->format('d/m/Y').'</b>';

                if ($telegram->send($chatId, $text, $payUrl, '📝 Ghi giao dịch')) {
                    $occ->update(['reminded_telegram_at' => now()]);
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
