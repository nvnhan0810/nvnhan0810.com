<?php

namespace Wallets\Lending\Application;

use App\Models\Holiday;
use App\Models\LoanCustomSchedule;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Wallets\Lending\Domain\AmortizationCalculator;

/**
 * Cầu nối tầng application cho AmortizationCalculator (domain thuần):
 * lấy dữ liệu hạ tầng (ngày nghỉ, lịch tùy chỉnh) rồi trả về Collection cho các handler.
 */
class AmortizationService
{
    public function __construct(private readonly AmortizationCalculator $calculator) {}

    public function calculate(
        $loanId,
        $principal,
        $annualRate,
        $months,
        $startDate,
        $fixedMonthlyPayment = null,
        $method = 'monthly',
        $paymentDay = null,
        $collectionFee = 0,
    ): Collection {
        $customRows = $method === 'custom' ? $this->customRows($loanId) : [];
        // Prefer persisted preview rows for Home Credit (edited schedule).
        if ($method === \Wallets\Lending\Domain\HomeCreditEmiCalculator::METHOD) {
            $persisted = $this->customRows($loanId);
            if ($persisted !== []) {
                $customRows = $persisted;
                $method = 'custom';
            }
        }
        $holidays = $method === 'daily' ? $this->holidays() : [];
        $start = $this->toImmutable($startDate);
        $resolvedPaymentDay = $paymentDay !== null
            ? (int) $paymentDay
            : (int) $start->format('j');

        $rows = $this->calculator->calculate(
            (float) $principal,
            (float) $annualRate,
            (int) $months,
            $start,
            $fixedMonthlyPayment !== null ? (float) $fixedMonthlyPayment : null,
            (string) $method,
            $customRows,
            $holidays,
            $resolvedPaymentDay,
            (float) $collectionFee,
        );

        return collect($rows);
    }

    public function adjustDueDateForNonWorkingDays(DateTimeInterface $date): DateTimeImmutable
    {
        return $this->calculator->adjustForNonWorkingDays(
            $this->toImmutable($date),
            $this->holidays(),
        );
    }

    /**
     * @return list<array{month_index:int,due_date:?string,payment:?float,principal:float,interest:float,fee:float}>
     */
    private function customRows($loanId): array
    {
        return LoanCustomSchedule::where('loan_id', $loanId)
            ->orderBy('month_index')
            ->get()
            ->map(fn (LoanCustomSchedule $row) => [
                'month_index' => (int) $row->month_index,
                'due_date' => optional($row->due_date ?? $row->paid_at)->toDateString(),
                'payment' => $row->payment !== null ? (float) $row->payment : null,
                'principal' => (float) $row->principal,
                'interest' => (float) $row->interest,
                'fee' => (float) ($row->fee ?? 0),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function holidays(): array
    {
        return Holiday::pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->all();
    }

    private function toImmutable($date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        return new DateTimeImmutable((string) $date);
    }
}
