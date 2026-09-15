<?php

namespace Wallets\RecurringPlanning\Application;

use App\Models\RecurringItem;
use App\Models\RecurringOccurrence;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringItemService
{
    public function __construct(
        private readonly RecurringOccurrenceGenerator $generator,
        private readonly RecurringOccurrenceStateService $stateService,
    ) {}

    /**
     * Kỳ thu/chi sắp/đang tới hạn dựa trên recurring_occurrences (gồm cả kỳ quá hạn).
     * Sinh kỳ còn thiếu + cập nhật trạng thái trước khi lấy.
     *
     * @return Collection<int, object>
     */
    public function upcoming(int $userId, int $withinDays, ?Carbon $from = null): Collection
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $horizon = $from->copy()->addDays($withinDays);

        RecurringItem::query()
            ->forUser($userId)
            ->active()
            ->get()
            ->each(fn (RecurringItem $item) => $this->generator->ensure($item, $horizon));

        $this->stateService->transitionStatuses($userId);

        return $this->stateService->upcomingOccurrences($userId, $withinDays)
            ->map(function (RecurringOccurrence $occ) use ($from) {
                $item = $occ->recurringItem;

                return (object) [
                    'occurrence_id' => $occ->id,
                    'item_id' => $occ->recurring_item_id,
                    'name' => $item?->name ?? 'Khoản cố định',
                    'type' => $item?->type ?? 'expense',
                    'type_label' => $item?->typeLabel() ?? 'Chi cố định',
                    'amount' => (float) $occ->expected_amount,
                    'due_date' => $occ->due_date,
                    'days_until' => (int) $from->diffInDays($occ->due_date, false),
                    'insufficient_funds' => false,
                    'wallet' => null,
                ];
            })
            ->sortBy('due_date')
            ->values();
    }
}
