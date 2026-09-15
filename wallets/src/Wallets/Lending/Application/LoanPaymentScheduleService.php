<?php

namespace Wallets\Lending\Application;

use App\Models\Loan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanPaymentScheduleService
{
    public function principalReductionCutoff(): Carbon
    {
        return Carbon::parse(config('loans.principal_reduction_cutoff'))->startOfDay();
    }

    public function paymentDay(Loan $loan): int
    {
        if ($loan->payment_day) {
            return (int) $loan->payment_day;
        }

        return (int) $loan->started_at->day;
    }

    public function periodDueDate(Loan $loan, array $scheduleRow): Carbon
    {
        if (in_array($loan->interest_calculation_method ?? 'monthly', ['daily', 'homecredit'], true)) {
            $date = $scheduleRow['date'] ?? null;
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance(\DateTime::createFromInterface($date))->startOfDay();
            }
            if (! empty($scheduleRow['due_date'])) {
                return Carbon::parse($scheduleRow['due_date'])->startOfDay();
            }
        }

        $base = Carbon::parse($scheduleRow['theoretical_date'] ?? $scheduleRow['date']);
        $day = min($this->paymentDay($loan), $base->daysInMonth);
        $candidate = $base->copy()->day($day)->startOfDay();

        return $candidate;
    }

    /**
     * @return array{period_due_date: Carbon, schedule_month_index: int, is_early: bool, reduces_principal: bool, kind: string}
     */
    public function resolvePeriodForPayment(Loan $loan, Carbon $paidAt, Collection $schedule): array
    {
        $paidAt = $paidAt->copy()->startOfDay();

        if ($schedule->isEmpty()) {
            return [
                'period_due_date' => $paidAt,
                'schedule_month_index' => 0,
                'is_early' => false,
                'reduces_principal' => false,
                'kind' => Payment::KIND_PERIOD,
            ];
        }

        $match = null;
        foreach ($schedule as $row) {
            $due = $this->periodDueDate($loan, $row);
            if ($paidAt->lte($due)) {
                $match = ['row' => $row, 'due' => $due];
                break;
            }
        }

        if (! $match) {
            $last = $schedule->last();
            $match = [
                'row' => $last,
                'due' => $this->periodDueDate($loan, $last),
            ];
        }

        $due = $match['due'];
        $monthIndex = (int) $match['row']['month_index'];
        $isEarly = $paidAt->lt($due);
        $reducesPrincipal = $this->shouldReducePrincipal($paidAt, $due, $isEarly);

        return [
            'period_due_date' => $due,
            'schedule_month_index' => $monthIndex,
            'is_early' => $isEarly,
            'reduces_principal' => $reducesPrincipal,
            'kind' => $isEarly ? Payment::KIND_EARLY : Payment::KIND_PERIOD,
        ];
    }

    public function shouldReducePrincipal(Carbon $paidAt, Carbon $periodDue, bool $isEarly): bool
    {
        if ($isEarly) {
            return false;
        }

        if ($paidAt->lt($this->principalReductionCutoff())) {
            return false;
        }

        return $paidAt->gte($periodDue);
    }

    public function effectiveMonthsPaid(Loan $loan, Collection $schedule, Collection $payments): int
    {
        if ($schedule->isEmpty()) {
            return 0;
        }

        $maxIndex = (int) $schedule->max('month_index');
        $fromPayments = (int) $payments
            ->where('reduces_principal', true)
            ->max('schedule_month_index');

        $fromSchedules = (int) $loan->customSchedules()
            ->where('status', \App\Models\LoanCustomSchedule::STATUS_PAID)
            ->max('month_index');

        $fromDueDates = $this->monthsElapsedByDueDate($loan, $schedule);

        $cutoff = $this->principalReductionCutoff();

        if (Carbon::today()->gte($cutoff)) {
            return min(max($fromPayments, $fromSchedules, (int) $loan->months_paid, $fromDueDates), $maxIndex);
        }

        $legacy = $loan->months_paid > 0
            ? (int) $loan->months_paid
            : (int) $loan->started_at->diffInMonths(Carbon::now());

        return min(max($legacy, $fromPayments, $fromSchedules, $fromDueDates), $maxIndex);
    }

    /**
     * Kỳ có ngày đến hạn đã qua (trước hôm nay) — dùng để tự tiến months_paid / lãi còn lại.
     */
    public function monthsElapsedByDueDate(Loan $loan, Collection $schedule): int
    {
        $today = Carbon::today()->startOfDay();
        $elapsed = 0;

        foreach ($schedule as $row) {
            $due = $this->periodDueDate($loan, $row);
            if ($due->lt($today)) {
                $elapsed = max($elapsed, (int) $row['month_index']);
            }
        }

        return $elapsed;
    }

    public function remainingPrincipalAt(Loan $loan, Collection $schedule, int $monthsPassed): float
    {
        if ($schedule->isEmpty()) {
            return (float) $loan->principal_amount;
        }

        if ($monthsPassed <= 0) {
            return (float) $loan->principal_amount;
        }

        $lastPaidPeriod = $schedule->firstWhere('month_index', $monthsPassed);

        if ($lastPaidPeriod !== null) {
            return (float) ($lastPaidPeriod['remaining_principal'] ?? 0);
        }

        return (float) ($schedule->last()['remaining_principal'] ?? 0);
    }

    public function principalBeforePeriod(Collection $schedule, int $monthIndex): float
    {
        $previous = $schedule->firstWhere('month_index', $monthIndex - 1);

        if ($previous) {
            return (float) $previous['remaining_principal'];
        }

        $first = $schedule->first();

        return (float) ($first['remaining_principal'] ?? 0) + (float) ($first['principal'] ?? 0);
    }

    /**
     * @return Collection<int, array{type: string, payment?: Payment, period?: array, is_paid?: bool, note?: string}>
     */
    public function buildTimeline(Loan $loan, Collection $schedule, Collection $payments, int $monthsPassed): Collection
    {
        $timeline = collect();

        foreach ($schedule as $row) {
            $due = $this->periodDueDate($loan, $row);
            $earlyPayments = $payments
                ->where('kind', Payment::KIND_EARLY)
                ->filter(fn (Payment $p) => $p->period_due_date && $p->period_due_date->isSameDay($due))
                ->sortBy('paid_at');

            foreach ($earlyPayments as $payment) {
                $prevPrincipal = $this->principalBeforePeriod($schedule, (int) $row['month_index']);
                $timeline->push([
                    'type' => 'early',
                    'payment' => $payment,
                    'period' => $row,
                    'period_due_date' => $due,
                    'note' => 'Thanh toán trước · Gốc còn lại (kỳ trước): '.number_format($prevPrincipal, 0).' ₫',
                ]);
            }

            $timeline->push([
                'type' => 'period',
                'period' => $row,
                'period_due_date' => $due,
                'is_paid' => $row['month_index'] <= $monthsPassed,
                'period_payment' => $payments->first(fn (Payment $p) => $p->reduces_principal
                    && (int) $p->schedule_month_index === (int) $row['month_index']),
            ]);
        }

        return $timeline;
    }

    /**
     * Normalize timeline dates/models for Inertia JSON (avoid DateTime → [object Object]).
     *
     * @param  Collection<int, array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    public function serializeTimeline(Collection $timeline): array
    {
        return $timeline->map(function (array $row): array {
            $period = $row['period'] ?? null;
            if (is_array($period)) {
                $period = [
                    ...$period,
                    'date' => $this->toDateString($period['date'] ?? null),
                    'theoretical_date' => $this->toDateString($period['theoretical_date'] ?? null),
                ];
            }

            $payment = $row['payment'] ?? null;
            if ($payment instanceof Payment) {
                $payment = [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => optional($payment->paid_at)?->toDateString(),
                    'note' => $payment->note,
                ];
            }

            $periodPayment = $row['period_payment'] ?? null;
            if ($periodPayment instanceof Payment) {
                $periodPayment = [
                    'id' => $periodPayment->id,
                    'amount' => (float) $periodPayment->amount,
                    'paid_at' => optional($periodPayment->paid_at)?->toDateString(),
                ];
            }

            return [
                'type' => $row['type'],
                'period' => $period,
                'period_due_date' => $this->toDateString($row['period_due_date'] ?? null),
                'is_paid' => (bool) ($row['is_paid'] ?? false),
                'period_payment' => $periodPayment,
                'payment' => $payment,
                'note' => $row['note'] ?? null,
            ];
        })->values()->all();
    }

    public function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toDateString();
        }

        return null;
    }

    public function hasEarlyPaymentForDueDate(Loan $loan, Carbon $dueDate): bool
    {
        return $loan->payments()
            ->where('kind', Payment::KIND_EARLY)
            ->whereDate('period_due_date', $dueDate->toDateString())
            ->exists();
    }
}
