<?php

namespace Wallets\Lending\Application;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use Carbon\Carbon;

/**
 * Vật chất hóa lịch kỳ trả vào bảng loan_custom_schedules cho khoản vay ngân hàng.
 * Kỳ đến hạn <= hôm nay (khi tạo hồi tố) được tự đánh dấu đã trả (không sinh giao dịch ví).
 */
class LoanScheduleGenerator
{
    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly LoanPaymentScheduleService $paymentSchedule,
    ) {}

    public function generate(Loan $loan, bool $backfillPast = true): void
    {
        if ($loan->type !== 'bank') {
            return;
        }

        $schedule = $this->amortization->calculate(
            $loan->id,
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->term_months,
            $loan->started_at,
            $loan->monthly_payment,
            $loan->interest_calculation_method ?? 'monthly',
            $loan->payment_day,
            $loan->collection_fee ?? 0,
        );

        if ($schedule->isEmpty()) {
            return;
        }

        $today = Carbon::today()->startOfDay();
        $paidCount = 0;

        foreach ($schedule as $row) {
            $due = $this->paymentSchedule->periodDueDate($loan, $row);
            $existing = LoanCustomSchedule::query()
                ->where('loan_id', $loan->id)
                ->where('month_index', (int) $row['month_index'])
                ->first();
            $isPast = $backfillPast && $due->lte($today);
            $wasPaid = $existing?->status === LoanCustomSchedule::STATUS_PAID;
            $markPaid = $wasPaid || $isPast;

            LoanCustomSchedule::updateOrCreate(
                ['loan_id' => $loan->id, 'month_index' => (int) $row['month_index']],
                [
                    'user_id' => $loan->user_id,
                    'due_date' => $due->toDateString(),
                    'payment' => $row['payment'],
                    'principal' => $row['principal'],
                    'interest' => $row['interest'],
                    'fee' => $row['fee'] ?? 0,
                    'remaining_principal' => $row['remaining_principal'],
                    'status' => $markPaid ? LoanCustomSchedule::STATUS_PAID : LoanCustomSchedule::STATUS_PENDING,
                    'paid_amount' => $markPaid ? ($existing?->paid_amount ?? $row['payment']) : null,
                    'paid_at' => $markPaid ? ($existing?->paid_at?->toDateString() ?? $due->toDateString()) : null,
                ]
            );

            if ($markPaid) {
                $paidCount++;
            }
        }

        if ($paidCount > 0) {
            $loan->update(['months_paid' => max((int) $loan->months_paid, $paidCount)]);
        }
    }

    /**
     * Mark already-persisted periods with due_date < today as paid (ongoing auto-progress).
     * Due today stays open so reminders / ghi trả vẫn hoạt động.
     */
    public function backfillPastPeriods(Loan $loan): void
    {
        if ($loan->type !== 'bank') {
            return;
        }

        $today = Carbon::today()->startOfDay();
        $paidCount = 0;

        $periods = LoanCustomSchedule::query()
            ->where('loan_id', $loan->id)
            ->orderBy('month_index')
            ->get();

        foreach ($periods as $period) {
            if (! $period->due_date) {
                continue;
            }
            $due = Carbon::parse($period->due_date)->startOfDay();
            // Chỉ kỳ đã qua ngày đến hạn (không gồm hôm nay — hôm nay vẫn nhắc / ghi trả).
            if ($due->lt($today) && $period->status !== LoanCustomSchedule::STATUS_PAID) {
                $period->update([
                    'status' => LoanCustomSchedule::STATUS_PAID,
                    'paid_amount' => $period->paid_amount ?? $period->payment,
                    'paid_at' => $period->paid_at?->toDateString() ?? $due->toDateString(),
                ]);
                $paidCount++;
            } elseif ($period->status === LoanCustomSchedule::STATUS_PAID) {
                $paidCount++;
            }
        }

        if ($paidCount > 0) {
            $loan->update(['months_paid' => max((int) $loan->months_paid, $paidCount)]);
        }
    }
}
