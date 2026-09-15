<?php

namespace Wallets\Reporting\Domain;

use DateTimeImmutable;

/**
 * Chiếu khoản chi cố định + kỳ trả vay chưa tất toán vào các ngày đến hạn trong khoảng.
 */
final class FixedExpenseOccurrenceProjector
{
    /**
     * @param  list<array{id:int|string,name:string,amount:float|int|string,day_of_month:int,effective_from:?string,ends_at:?string,is_active:bool}>  $recurring
     * @param  list<array{id:int|string,name:string,type:string,is_settled:bool,monthly_payment:float|int|string|null,payment_day:int|null,started_at:string|null,term_months:int|null,schedules:list<array{due_date:string,payment:float|int|string,status?:string}>}>  $loans
     * @return list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>
     */
    public function project(
        array $recurring,
        array $loans,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEnd,
    ): array {
        $rangeStart = $rangeStart->setTime(0, 0, 0);
        $rangeEnd = $rangeEnd->setTime(0, 0, 0);
        $events = [];

        foreach ($recurring as $item) {
            if (! ($item['is_active'] ?? false)) {
                continue;
            }
            foreach ($this->recurringDues($item, $rangeStart, $rangeEnd) as $event) {
                $events[] = $event;
            }
        }

        foreach ($loans as $loan) {
            if ($loan['is_settled'] ?? false) {
                continue;
            }
            if (($loan['type'] ?? '') === 'lend') {
                continue;
            }
            foreach ($this->loanDues($loan, $rangeStart, $rangeEnd) as $event) {
                $events[] = $event;
            }
        }

        usort($events, static function (array $a, array $b): int {
            return [$a['due_date'], $a['name']] <=> [$b['due_date'], $b['name']];
        });

        return $events;
    }

    /**
     * @param  array{id:int|string,name:string,amount:float|int|string,day_of_month:int,effective_from:?string,ends_at:?string,is_active:bool}  $item
     * @return list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>
     */
    private function recurringDues(array $item, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $events = [];
        $dayOfMonth = max(1, min(31, (int) $item['day_of_month']));
        $effectiveFrom = $this->parseDate($item['effective_from'] ?? null);
        $endsAt = $this->parseDate($item['ends_at'] ?? null);
        $cursor = $rangeStart->modify('first day of this month');

        while ($cursor <= $rangeEnd) {
            $due = $this->dateOnDay($cursor, $dayOfMonth);
            if ($due >= $rangeStart && $due <= $rangeEnd) {
                $inWindow = ($effectiveFrom === null || $due >= $effectiveFrom)
                    && ($endsAt === null || $due <= $endsAt);
                if ($inWindow) {
                    $events[] = [
                        'source' => FixedExpenseSource::RECURRING,
                        'source_id' => $item['id'],
                        'name' => (string) $item['name'],
                        'amount' => (float) $item['amount'],
                        'due_date' => $due->format('Y-m-d'),
                        'type_label' => 'Chi cố định',
                    ];
                }
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $events;
    }

    /**
     * @param  array{id:int|string,name:string,type:string,is_settled:bool,monthly_payment:float|int|string|null,payment_day:int|null,started_at:string|null,term_months:int|null,schedules:list<array{due_date:string,payment:float|int|string,status?:string}>}  $loan
     * @return list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>
     */
    private function loanDues(array $loan, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $schedules = $loan['schedules'] ?? [];
        if ($schedules !== []) {
            return $this->scheduleDues($loan, $schedules, $rangeStart, $rangeEnd);
        }

        return $this->syntheticMonthlyLoanDues($loan, $rangeStart, $rangeEnd);
    }

    /**
     * @param  array{id:int|string,name:string,type:string}  $loan
     * @param  list<array{due_date:string,payment:float|int|string,status?:string}>  $schedules
     * @return list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>
     */
    private function scheduleDues(array $loan, array $schedules, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $events = [];
        foreach ($schedules as $row) {
            $due = $this->parseDate($row['due_date'] ?? null);
            if ($due === null || $due < $rangeStart || $due > $rangeEnd) {
                continue;
            }
            if (($row['status'] ?? '') === 'skipped') {
                continue;
            }
            $events[] = [
                'source' => FixedExpenseSource::LOAN,
                'source_id' => $loan['id'],
                'name' => (string) $loan['name'],
                'amount' => (float) $row['payment'],
                'due_date' => $due->format('Y-m-d'),
                'type_label' => $this->loanTypeLabel((string) ($loan['type'] ?? 'bank')),
            ];
        }

        return $events;
    }

    /**
     * @param  array{id:int|string,name:string,type:string,monthly_payment:float|int|string|null,payment_day:int|null,started_at:string|null,term_months:int|null}  $loan
     * @return list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>
     */
    private function syntheticMonthlyLoanDues(array $loan, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $amount = (float) ($loan['monthly_payment'] ?? 0);
        if ($amount <= 0) {
            return [];
        }

        $startedAt = $this->parseDate($loan['started_at'] ?? null);
        if ($startedAt === null) {
            return [];
        }

        $paymentDay = (int) ($loan['payment_day'] ?? (int) $startedAt->format('j'));
        $paymentDay = max(1, min(31, $paymentDay));
        $termMonths = $loan['term_months'] ?? null;
        $termEnd = null;
        if ($termMonths !== null && (int) $termMonths > 0) {
            $termEnd = $startedAt->modify('+'.((int) $termMonths).' months');
        }

        $events = [];
        $cursor = $rangeStart->modify('first day of this month');
        while ($cursor <= $rangeEnd) {
            $due = $this->dateOnDay($cursor, $paymentDay);
            if ($due >= $rangeStart && $due <= $rangeEnd && $due >= $startedAt) {
                if ($termEnd === null || $due <= $termEnd) {
                    $events[] = [
                        'source' => FixedExpenseSource::LOAN,
                        'source_id' => $loan['id'],
                        'name' => (string) $loan['name'],
                        'amount' => $amount,
                        'due_date' => $due->format('Y-m-d'),
                        'type_label' => $this->loanTypeLabel((string) ($loan['type'] ?? 'borrow')),
                    ];
                }
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $events;
    }

    private function loanTypeLabel(string $type): string
    {
        return match ($type) {
            'borrow' => 'Trả nợ mượn',
            'bank' => 'Trả khoản vay',
            default => 'Trả khoản vay',
        };
    }

    private function dateOnDay(DateTimeImmutable $monthStart, int $dayOfMonth): DateTimeImmutable
    {
        $daysInMonth = (int) $monthStart->format('t');
        $day = min($dayOfMonth, $daysInMonth);

        return $monthStart->setDate(
            (int) $monthStart->format('Y'),
            (int) $monthStart->format('m'),
            $day,
        );
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value->setTime(0, 0, 0);
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $value, 0, 10));

        return $parsed ? $parsed->setTime(0, 0, 0) : null;
    }
}
