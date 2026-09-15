<?php

namespace Wallets\Lending\Domain;

use DateTimeImmutable;

/**
 * Home Credit–style EMI: Actual/365, fixed payment day (no holiday shift),
 * collection fee, interest rounded up to tens, residual dumped into last period.
 */
final class HomeCreditEmiCalculator
{
    public const METHOD = 'homecredit';

    public const DEFAULT_EARLY_SETTLEMENT_PENALTY_RATE = 0.05;

    /**
     * @return list<array{
     *   month_index:int,
     *   date:DateTimeImmutable,
     *   theoretical_date:DateTimeImmutable,
     *   is_adjusted:bool,
     *   days:int,
     *   payment:float,
     *   interest:float,
     *   principal:float,
     *   fee:float,
     *   remaining_principal:float
     * }>
     */
    public function calculate(
        float $principal,
        float $annualRate,
        int $months,
        DateTimeImmutable $startDate,
        float $emi,
        float $collectionFee = 0.0,
        int $paymentDay = 0,
    ): array {
        if ($months < 1 || $principal <= 0 || $emi <= 0) {
            return [];
        }

        if ($paymentDay <= 0) {
            $paymentDay = (int) $startDate->format('j');
        }

        $collectionFee = max(0.0, round($collectionFee, 0));
        $emi = round($emi, 0);
        $balance = round($principal, 0);
        $schedule = [];
        $prevDate = $startDate;

        for ($i = 1; $i <= $months; $i++) {
            $dueDate = $this->dueDateForPeriod($startDate, $i, $paymentDay);
            $days = $this->diffInDays($prevDate, $dueDate);
            $isLast = $i === $months;
            $fee = $collectionFee;

            if ($balance <= 0) {
                $schedule[] = [
                    'month_index' => $i,
                    'date' => $dueDate,
                    'theoretical_date' => $dueDate,
                    'is_adjusted' => false,
                    'days' => $days,
                    'payment' => 0.0,
                    'interest' => 0.0,
                    'principal' => 0.0,
                    'fee' => 0.0,
                    'remaining_principal' => 0.0,
                ];
                $prevDate = $dueDate;
                continue;
            }

            $rawInterest = $balance * ($annualRate / 100) * $days / 365;
            $interest = $this->ceilToTens($rawInterest);

            if ($isLast) {
                $principalPayment = $balance;
                $payment = round($principalPayment + $interest + $fee, 0);
            } else {
                $allocatable = max(0.0, $emi - $fee);
                $principalPayment = round($allocatable - $interest, 0);
                if ($principalPayment < 0) {
                    $principalPayment = 0.0;
                    $interest = min($interest, $allocatable);
                }
                if ($principalPayment > $balance) {
                    $principalPayment = $balance;
                }
                $payment = round($principalPayment + $interest + $fee, 0);
            }

            $balance = round($balance - $principalPayment, 0);
            if ($balance < 0) {
                $balance = 0.0;
            }

            $schedule[] = [
                'month_index' => $i,
                'date' => $dueDate,
                'theoretical_date' => $dueDate,
                'is_adjusted' => false,
                'days' => $days,
                'payment' => $payment,
                'interest' => $interest,
                'principal' => $principalPayment,
                'fee' => $fee,
                'remaining_principal' => $balance,
            ];

            $prevDate = $dueDate;
        }

        return $this->forceLastPeriodZero($schedule, $collectionFee);
    }

    /**
     * Rebuild principal + remaining after user edits interest/fee on preview rows.
     *
     * @param  list<array{month_index?:int,due_date?:string,date?:DateTimeImmutable,days?:int,interest:float|int,fee:float|int,payment?:float|int}>  $rows
     * @return list<array<string,mixed>>
     */
    public function recalculateFromOverrides(float $principal, array $rows, float $defaultEmi): array
    {
        $balance = round($principal, 0);
        $result = [];
        $count = count($rows);

        foreach ($rows as $idx => $row) {
            $interest = max(0.0, round((float) ($row['interest'] ?? 0), 0));
            $fee = max(0.0, round((float) ($row['fee'] ?? 0), 0));
            $isLast = $idx === $count - 1;

            $date = $row['date'] ?? null;
            if (! $date instanceof DateTimeImmutable) {
                $dateStr = (string) ($row['due_date'] ?? $row['paid_at'] ?? 'now');
                $date = new DateTimeImmutable($dateStr);
            }

            $days = isset($row['days']) ? (int) $row['days'] : null;

            if ($isLast) {
                $principalPayment = $balance;
                $payment = round($principalPayment + $interest + $fee, 0);
            } else {
                $emi = isset($row['payment']) ? round((float) $row['payment'], 0) : round($defaultEmi, 0);
                // Keep EMI total stable when possible: payment = principal + interest + fee
                $allocatable = max(0.0, $emi - $fee);
                $principalPayment = round($allocatable - $interest, 0);
                if ($principalPayment < 0) {
                    $principalPayment = 0.0;
                }
                if ($principalPayment > $balance) {
                    $principalPayment = $balance;
                }
                $payment = round($principalPayment + $interest + $fee, 0);
            }

            $balance = round($balance - $principalPayment, 0);
            if ($balance < 0) {
                $balance = 0.0;
            }

            $result[] = [
                'month_index' => (int) ($row['month_index'] ?? ($idx + 1)),
                'date' => $date,
                'theoretical_date' => $date,
                'is_adjusted' => false,
                'days' => $days,
                'payment' => $payment,
                'interest' => $interest,
                'principal' => $principalPayment,
                'fee' => $fee,
                'remaining_principal' => $balance,
            ];
        }

        return $this->forceLastPeriodZero($result, null);
    }

    /**
     * @return array{remaining_principal:float,accrued_interest:float,penalty:float,total:float,days:int}
     */
    public function earlySettlement(
        float $remainingPrincipal,
        float $annualRate,
        DateTimeImmutable $lastPaymentDate,
        DateTimeImmutable $settlementDate,
        float $penaltyRate = self::DEFAULT_EARLY_SETTLEMENT_PENALTY_RATE,
    ): array {
        $days = max(0, $this->diffInDays($lastPaymentDate, $settlementDate));
        $rawInterest = $remainingPrincipal * ($annualRate / 100) * $days / 365;
        $accruedInterest = $this->ceilToTens($rawInterest);
        $penalty = $this->ceilToTens($remainingPrincipal * $penaltyRate);

        return [
            'remaining_principal' => round($remainingPrincipal, 0),
            'accrued_interest' => $accruedInterest,
            'penalty' => $penalty,
            'total' => round($remainingPrincipal + $accruedInterest + $penalty, 0),
            'days' => $days,
        ];
    }

    public function ceilToTens(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return (float) (ceil($amount / 10) * 10);
    }

    /**
     * @param  list<array<string,mixed>>  $schedule
     * @return list<array<string,mixed>>
     */
    private function forceLastPeriodZero(array $schedule, ?float $collectionFee): array
    {
        if ($schedule === []) {
            return $schedule;
        }

        $lastIdx = array_key_last($schedule);
        $remaining = (float) $schedule[$lastIdx]['remaining_principal'];
        if ($remaining == 0.0) {
            return $schedule;
        }

        $schedule[$lastIdx]['principal'] = round((float) $schedule[$lastIdx]['principal'] + $remaining, 0);
        $fee = $collectionFee !== null
            ? $collectionFee
            : (float) ($schedule[$lastIdx]['fee'] ?? 0);
        $schedule[$lastIdx]['fee'] = $fee;
        $schedule[$lastIdx]['payment'] = round(
            (float) $schedule[$lastIdx]['principal']
            + (float) $schedule[$lastIdx]['interest']
            + $fee,
            0,
        );
        $schedule[$lastIdx]['remaining_principal'] = 0.0;

        return $schedule;
    }

    private function dueDateForPeriod(DateTimeImmutable $startDate, int $periodIndex, int $paymentDay): DateTimeImmutable
    {
        $year = (int) $startDate->format('Y');
        $month = (int) $startDate->format('m') + $periodIndex;
        while ($month > 12) {
            $month -= 12;
            $year++;
        }

        $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        $day = min($paymentDay, $daysInMonth);

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function diffInDays(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }
}
