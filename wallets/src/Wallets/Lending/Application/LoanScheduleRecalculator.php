<?php

namespace Wallets\Lending\Application;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;

/**
 * Tính lại kỳ trả chưa thanh toán khi danh sách ngày lễ thay đổi (Actual/365).
 */
class LoanScheduleRecalculator
{
    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly LoanPaymentScheduleService $paymentSchedule,
        private readonly LoanScheduleStateService $scheduleState,
    ) {}

    public function recalculateUnsettledDailyLoans(): int
    {
        $updated = 0;

        Loan::query()
            ->where('type', 'bank')
            ->where('is_settled', false)
            ->where('interest_calculation_method', 'daily')
            ->orderBy('id')
            ->each(function (Loan $loan) use (&$updated) {
                if ($this->recalculateLoan($loan)) {
                    $updated++;
                }
            });

        if ($updated > 0) {
            $this->scheduleState->transitionStatuses();
        }

        return $updated;
    }

    public function recalculateLoan(Loan $loan): bool
    {
        if ($loan->type !== 'bank' || $loan->is_settled || ($loan->interest_calculation_method ?? 'monthly') !== 'daily') {
            return false;
        }

        $schedule = $this->amortization->calculate(
            $loan->id,
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->term_months,
            $loan->started_at,
            $loan->monthly_payment,
            'daily',
            $loan->payment_day,
        );

        if ($schedule->isEmpty()) {
            return false;
        }

        /** @var Collection<int, array<string, mixed>> $scheduleByIndex */
        $scheduleByIndex = $schedule->keyBy('month_index');

        $openPeriods = LoanCustomSchedule::query()
            ->where('loan_id', $loan->id)
            ->whereIn('status', [
                LoanCustomSchedule::STATUS_PENDING,
                LoanCustomSchedule::STATUS_DUE,
                LoanCustomSchedule::STATUS_OVERDUE,
            ])
            ->get();

        if ($openPeriods->isEmpty()) {
            return false;
        }

        $changed = false;

        foreach ($openPeriods as $period) {
            $row = $scheduleByIndex->get((int) $period->month_index);
            if ($row === null) {
                continue;
            }

            $dueDate = $this->paymentSchedule->periodDueDate($loan, $row)->toDateString();

            $period->update([
                'due_date' => $dueDate,
                'payment' => $row['payment'],
                'principal' => $row['principal'],
                'interest' => $row['interest'],
                'remaining_principal' => $row['remaining_principal'],
            ]);

            $changed = true;
        }

        return $changed;
    }
}
