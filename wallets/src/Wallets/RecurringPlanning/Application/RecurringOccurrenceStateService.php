<?php

namespace Wallets\RecurringPlanning\Application;

use App\Models\RecurringOccurrence;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringOccurrenceStateService
{
    /**
     * Cập nhật trạng thái kỳ chưa ghi theo mốc hôm nay: due (đúng ngày), overdue (quá hạn), pending (tương lai).
     */
    public function transitionStatuses(?int $userId = null): void
    {
        $today = Carbon::today()->toDateString();
        $open = RecurringOccurrence::openStatuses();

        $base = fn () => RecurringOccurrence::query()
            ->whereIn('status', $open)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));

        $base()->whereDate('due_date', '<', $today)->update(['status' => RecurringOccurrence::STATUS_OVERDUE]);
        $base()->whereDate('due_date', '=', $today)->update(['status' => RecurringOccurrence::STATUS_DUE]);
        $base()->whereDate('due_date', '>', $today)->update(['status' => RecurringOccurrence::STATUS_PENDING]);
    }

    public function markPosted(RecurringOccurrence $occurrence, Transaction $transaction): void
    {
        $occurrence->markPosted($transaction);
    }

    /**
     * Kỳ đến hạn (<= hôm nay) chưa ghi, chưa gửi Telegram hôm nay.
     *
     * @return Collection<int, RecurringOccurrence>
     */
    public function dueTodayUnremindedOccurrences(int $userId): Collection
    {
        $today = Carbon::today();

        return RecurringOccurrence::query()
            ->with(['recurringItem'])
            ->where('user_id', $userId)
            ->whereIn('status', [RecurringOccurrence::STATUS_DUE, RecurringOccurrence::STATUS_OVERDUE])
            ->whereDate('due_date', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('reminded_telegram_at')
                    ->orWhereDate('reminded_telegram_at', '<', $today->toDateString());
            })
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Kỳ sắp/đang tới hạn cho dashboard: mở, đến hạn trong vòng $withinDays hoặc đã quá hạn.
     *
     * @return Collection<int, RecurringOccurrence>
     */
    public function upcomingOccurrences(int $userId, int $withinDays): Collection
    {
        $until = Carbon::today()->addDays($withinDays)->toDateString();

        return RecurringOccurrence::query()
            ->with(['recurringItem'])
            ->where('user_id', $userId)
            ->whereIn('status', RecurringOccurrence::openStatuses())
            ->whereDate('due_date', '<=', $until)
            ->orderBy('due_date')
            ->get();
    }
}
