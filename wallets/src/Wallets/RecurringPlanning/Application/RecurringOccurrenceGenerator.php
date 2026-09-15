<?php

namespace Wallets\RecurringPlanning\Application;

use App\Models\RecurringItem;
use App\Models\RecurringOccurrence;
use Carbon\Carbon;

/**
 * Vật chất hóa từng lần phát sinh hàng tháng của thu/chi cố định vào bảng recurring_occurrences.
 * Không backfill quá khứ: chỉ sinh kỳ có due_date từ hôm nay tới horizon.
 */
class RecurringOccurrenceGenerator
{
    public function ensure(RecurringItem $item, ?Carbon $horizon = null): void
    {
        if (! $item->is_active) {
            return;
        }

        $today = Carbon::today()->startOfDay();
        $horizon = ($horizon ?? $today->copy()->addDays(30))->copy()->endOfDay();

        $start = $today->copy();
        if ($item->effective_from && $item->effective_from->gt($start)) {
            $start = $item->effective_from->copy()->startOfDay();
        }

        $end = $horizon->copy();
        if ($item->ends_at && $item->ends_at->copy()->endOfDay()->lt($end)) {
            $end = $item->ends_at->copy()->endOfDay();
        }

        if ($start->gt($end)) {
            return;
        }

        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $due = $this->dueDateForMonth($item, $cursor);

            if ($due->gte($start) && $due->lte($end)) {
                $exists = RecurringOccurrence::query()
                    ->where('recurring_item_id', $item->id)
                    ->whereDate('due_date', $due->toDateString())
                    ->exists();

                if (! $exists) {
                    RecurringOccurrence::create([
                        'recurring_item_id' => $item->id,
                        'user_id' => $item->user_id,
                        'due_date' => $due->toDateString(),
                        'expected_amount' => $item->amount,
                        'status' => RecurringOccurrence::STATUS_PENDING,
                    ]);
                }
            }

            $cursor->addMonthNoOverflow();
        }
    }

    private function dueDateForMonth(RecurringItem $item, Carbon $month): Carbon
    {
        $day = min((int) $item->day_of_month, $month->daysInMonth);

        return $month->copy()->day($day)->startOfDay();
    }
}
