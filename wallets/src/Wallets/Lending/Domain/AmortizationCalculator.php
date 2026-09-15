<?php

namespace Wallets\Lending\Domain;

use DateTimeImmutable;

/**
 * Tính lịch trả góp thuần nghiệp vụ — KHÔNG phụ thuộc Laravel/Carbon/Eloquent.
 * Dữ liệu ngoài (ngày nghỉ, lịch tùy chỉnh) được tầng application bơm vào.
 *
 * Mỗi dòng trả về là mảng:
 *   month_index, date (DateTimeImmutable), theoretical_date (DateTimeImmutable),
 *   is_adjusted, days, payment, interest, principal, [fee], remaining_principal
 */
final class AmortizationCalculator
{
    private const SATURDAY = 6;

    private const SUNDAY = 0;

    /** Kỳ đầu vay daily: tối thiểu 33 ngày tính lãi Actual/365 (theo lịch ngân hàng). */
    private const MIN_FIRST_PERIOD_DAYS = 33;

    /**
     * @param  list<array{month_index:int,due_date:?string,payment:?float,principal:float,interest:float,fee?:float}>  $customRows  lịch tùy chỉnh (method='custom')
     * @param  list<string>  $holidays  danh sách ngày nghỉ dạng 'Y-m-d' (method='daily')
     * @return list<array<string,mixed>>
     */
    public function calculate(
        float $principal,
        float $annualRate,
        int $months,
        DateTimeImmutable $startDate,
        ?float $fixedMonthlyPayment = null,
        string $method = 'monthly',
        array $customRows = [],
        array $holidays = [],
        int $paymentDay = 0,
        float $collectionFee = 0.0,
    ): array {
        if ($method === 'custom') {
            return $this->buildCustomSchedule($principal, $customRows);
        }

        if ($method === HomeCreditEmiCalculator::METHOD) {
            return (new HomeCreditEmiCalculator)->calculate(
                $principal,
                $annualRate,
                $months,
                $startDate,
                (float) ($fixedMonthlyPayment ?? 0),
                $collectionFee,
                $paymentDay,
            );
        }

        $balance = $principal;
        $schedule = [];
        $prevDate = $startDate;

        if ($paymentDay <= 0) {
            $paymentDay = (int) $startDate->format('j');
        }

        if (! $fixedMonthlyPayment) {
            $monthlyRate = ($annualRate / 100) / 12;
            $fixedMonthlyPayment = ($principal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$months));
        }

        for ($i = 1; $i <= $months; $i++) {
            $theoreticalDate = $this->setDayOfMonth($startDate->modify("+{$i} months"), $paymentDay);
            $actualDate = $method === 'daily'
                ? $this->adjustForNonWorkingDays($theoreticalDate, $holidays)
                : $theoreticalDate;

            if ($method === 'daily' && $i === 1) {
                $minimumFirstDue = $startDate->modify('+'.self::MIN_FIRST_PERIOD_DAYS.' days');
                if ($actualDate < $minimumFirstDue) {
                    $actualDate = $minimumFirstDue;
                }
            }

            if ($method === 'daily') {
                $days = $this->diffInDays($prevDate, $actualDate);
                $interest = round($balance * ($annualRate / 100) * $days / 365, 0);
            } else {
                $monthlyRate = ($annualRate / 100) / 12;
                $interest = round($balance * $monthlyRate, 0);
                $days = $this->diffInDays($prevDate, $actualDate);
            }

            $payment = round($fixedMonthlyPayment, 0);

            if ($i == $months && $balance + $interest < $payment + 100000) {
                $payment = $balance + $interest;
            }

            $principalPayment = round($payment - $interest, 0);

            if ($principalPayment > $balance) {
                $principalPayment = $balance;
                $payment = $interest + $principalPayment;
            }

            $balance = round($balance - $principalPayment, 0);
            if ($balance < 0) {
                $balance = 0;
            }

            $schedule[] = [
                'month_index' => $i,
                'date' => $actualDate,
                'theoretical_date' => $theoreticalDate,
                'is_adjusted' => ! $this->isSameDay($theoreticalDate, $actualDate),
                'days' => $days,
                'payment' => $payment,
                'interest' => $interest,
                'principal' => $principalPayment,
                'remaining_principal' => $balance,
            ];

            $prevDate = $actualDate;
        }

        return $schedule;
    }

    /**
     * @param  list<string>  $holidays
     */
    public function adjustForNonWorkingDays(DateTimeImmutable $date, array $holidays): DateTimeImmutable
    {
        $adjusted = $date;
        $iterations = 0;

        while ($iterations < 10) {
            $dayOfWeek = (int) $adjusted->format('w');
            $dateString = $adjusted->format('Y-m-d');

            if ($dayOfWeek === self::SATURDAY || $dayOfWeek === self::SUNDAY || in_array($dateString, $holidays, true)) {
                $adjusted = $adjusted->modify('+1 day');
                $iterations++;

                continue;
            }

            break;
        }

        return $adjusted;
    }

    private function setDayOfMonth(DateTimeImmutable $date, int $day): DateTimeImmutable
    {
        $daysInMonth = (int) $date->format('t');
        $day = min($day, $daysInMonth);

        return $date->setDate((int) $date->format('Y'), (int) $date->format('m'), $day);
    }

    /**
     * @param  list<array{month_index:int,due_date:?string,payment:?float,principal:float,interest:float,fee?:float}>  $customRows
     * @return list<array<string,mixed>>
     */
    private function buildCustomSchedule(float $principal, array $customRows): array
    {
        if ($customRows === []) {
            return [];
        }

        $balance = $principal;
        $result = [];

        foreach ($customRows as $row) {
            $date = ! empty($row['due_date'])
                ? new DateTimeImmutable($row['due_date'])
                : new DateTimeImmutable('now');

            $principalPayment = (float) $row['principal'];
            $interest = (float) $row['interest'];
            $fee = (float) ($row['fee'] ?? 0);
            $payment = $row['payment'] ?? ($principalPayment + $interest + $fee);
            $balance = max($balance - $principalPayment, 0);

            $result[] = [
                'month_index' => $row['month_index'],
                'date' => $date,
                'theoretical_date' => $date,
                'is_adjusted' => false,
                'days' => null,
                'payment' => $payment,
                'interest' => $interest,
                'principal' => $principalPayment,
                'fee' => $fee,
                'remaining_principal' => $balance,
            ];
        }

        return $result;
    }

    private function diffInDays(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }

    private function isSameDay(DateTimeImmutable $a, DateTimeImmutable $b): bool
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }
}
