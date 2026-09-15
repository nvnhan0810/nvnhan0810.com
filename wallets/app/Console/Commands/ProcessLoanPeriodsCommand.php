<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Wallets\Lending\Application\LoanScheduleGenerator;
use Wallets\Lending\Application\LoanScheduleStateService;

class ProcessLoanPeriodsCommand extends Command
{
    protected $signature = 'loans:process-periods';

    protected $description = 'Backfill kỳ quá hạn = đã trả, cập nhật due/overdue, gửi Telegram nhắc kỳ tới hạn chưa trả';

    public function handle(
        LoanScheduleStateService $state,
        LoanScheduleGenerator $generator,
        TelegramNotifier $telegram,
    ): int {
        foreach (Loan::query()->where('type', 'bank')->where('is_settled', false)->cursor() as $loan) {
            $generator->backfillPastPeriods($loan);
        }

        $state->transitionStatuses();

        if (! $telegram->isConfigured()) {
            $this->warn('Chưa cấu hình TELEGRAM_BOT_TOKEN — bỏ qua gửi nhắc.');

            return self::SUCCESS;
        }

        $userIds = LoanCustomSchedule::query()
            ->whereIn('status', [LoanCustomSchedule::STATUS_DUE, LoanCustomSchedule::STATUS_OVERDUE])
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

            foreach ($state->dueTodayUnremindedPeriods($userId) as $period) {
                $loan = $period->loan;
                if (! $loan) {
                    continue;
                }

                $payUrl = URL::route('loans.index', array_filter([
                    'pay' => $loan->id,
                    'period' => $period->id,
                    'amount' => (int) $period->payment,
                ]));

                $overdue = $period->due_date->isPast() && ! $period->due_date->isToday();
                $heading = $overdue ? '⚠️ <b>Quá hạn trả khoản vay</b>' : '🔔 <b>Đến hạn trả khoản vay</b>';

                $text = $heading."\n\n"
                    .'Khoản vay: <b>'.e($loan->name)."</b>\n"
                    .'Số tiền kỳ này: <b>'.number_format((float) $period->payment, 0).' ₫</b>'."\n"
                    .'Ngày đến hạn: <b>'.$period->due_date->format('d/m/Y').'</b>';

                if ($telegram->send($chatId, $text, $payUrl, '💸 Tạo khoản trả')) {
                    $period->update(['reminded_telegram_at' => now()]);
                    $sent++;
                }
            }
        }

        $this->info("Đã gửi {$sent} nhắc Telegram.");

        return self::SUCCESS;
    }
}
