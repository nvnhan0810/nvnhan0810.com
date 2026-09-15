<?php

namespace Wallets\Lending\Application;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanPaymentReminderService
{
    public function __construct(
        private LoanPaymentScheduleService $scheduleService,
        private LoanScheduleStateService $stateService,
    ) {}

    /**
     * Kỳ trả sắp/đang tới hạn (theo bảng kỳ). Gom kỳ mở sớm nhất cho mỗi khoản vay,
     * bao gồm cả kỳ đã quá hạn (days_until âm).
     */
    public function upcomingLoanPayments(int $userId, ?int $withinDays = null): Collection
    {
        $withinDays = $withinDays ?? Setting::recurringAlertDays($userId);
        $from = Carbon::today()->startOfDay();

        return $this->stateService->upcomingPeriods($userId, $withinDays)
            ->groupBy('loan_id')
            ->map(function (Collection $periods) use ($from) {
                $period = $periods->sortBy('due_date')->first();
                $loan = $period->loan;

                if (! $loan || $loan->is_settled) {
                    return null;
                }

                if ($this->scheduleService->hasEarlyPaymentForDueDate($loan, $period->due_date)) {
                    return null;
                }

                $loan->setRelation('wallet', $loan->wallet);
                $loan->payment_due_date = $period->due_date;
                $loan->days_until_payment = (int) $from->diffInDays($period->due_date, false);
                $loan->next_period_id = $period->id;
                $loan->next_period_amount = (float) $period->payment;

                return $loan;
            })
            ->filter()
            ->sortBy('payment_due_date')
            ->values();
    }
}
