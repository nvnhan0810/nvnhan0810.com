<?php

namespace Wallets\Lending\Application;

use DateTimeImmutable;
use Wallets\Lending\Domain\HomeCreditEmiCalculator;

/**
 * Build / rebuild loan schedules for the create → preview flow (no persistence).
 */
final class LoanSchedulePreviewService
{
    public function __construct(
        private readonly HomeCreditEmiCalculator $homeCredit,
        private readonly AmortizationService $amortization,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function build(array $input): array
    {
        $method = (string) ($input['interest_calculation_method'] ?? 'monthly');
        $principal = (float) $input['principal_amount'];
        $rate = (float) ($input['interest_rate'] ?? 0);
        $months = (int) ($input['term_months'] ?? 0);
        $emi = (float) ($input['monthly_payment'] ?? 0);
        $fee = (float) ($input['collection_fee'] ?? 0);
        $paymentDay = (int) ($input['payment_day'] ?? 0);
        $start = new DateTimeImmutable((string) $input['started_at']);

        if ($method === HomeCreditEmiCalculator::METHOD) {
            $rows = $this->homeCredit->calculate($principal, $rate, $months, $start, $emi, $fee, $paymentDay);
        } else {
            $rows = $this->amortization->calculate(
                loanId: 0,
                principal: $principal,
                annualRate: $rate,
                months: $months,
                startDate: $start,
                fixedMonthlyPayment: $emi,
                method: $method,
                paymentDay: $paymentDay > 0 ? $paymentDay : null,
                collectionFee: $fee,
            )->all();
        }

        return $this->serializeRows($rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    public function rebuildFromOverrides(array $input, array $overrides): array
    {
        $principal = (float) $input['principal_amount'];
        $emi = (float) ($input['monthly_payment'] ?? 0);
        $rows = $this->homeCredit->recalculateFromOverrides($principal, $overrides, $emi);

        return $this->serializeRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function serializeRows(array $rows): array
    {
        return array_values(array_map(static function (array $row): array {
            $date = $row['date'] ?? null;
            $dateStr = $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) ($row['due_date'] ?? '');

            return [
                'month_index' => (int) $row['month_index'],
                'due_date' => $dateStr,
                'due_date_label' => $dateStr !== ''
                    ? (new DateTimeImmutable($dateStr))->format('d/m/Y')
                    : '',
                'days' => isset($row['days']) ? (int) $row['days'] : null,
                'payment' => (float) $row['payment'],
                'interest' => (float) $row['interest'],
                'principal' => (float) $row['principal'],
                'fee' => (float) ($row['fee'] ?? 0),
                'remaining_principal' => (float) $row['remaining_principal'],
            ];
        }, $rows));
    }
}
