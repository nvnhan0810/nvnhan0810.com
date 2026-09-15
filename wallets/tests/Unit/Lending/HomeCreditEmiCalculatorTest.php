<?php

namespace Tests\Unit\Lending;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wallets\Lending\Domain\HomeCreditEmiCalculator;

class HomeCreditEmiCalculatorTest extends TestCase
{
    private HomeCreditEmiCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new HomeCreditEmiCalculator;
    }

    #[Test]
    public function should_round_interest_up_to_tens(): void
    {
        $this->assertSame(4744770.0, $this->calc->ceilToTens(4744768.89));
        $this->assertSame(10.0, $this->calc->ceilToTens(1.0));
        $this->assertSame(0.0, $this->calc->ceilToTens(0.0));
    }

    #[Test]
    public function should_use_actual_365_so_31_day_month_costs_more_interest_than_30(): void
    {
        $may31 = $this->calc->calculate(
            principal: 100_000_000,
            annualRate: 35,
            months: 1,
            startDate: new DateTimeImmutable('2026-04-30'),
            emi: 10_000_000,
            collectionFee: 0,
            paymentDay: 31,
        );
        $apr30 = $this->calc->calculate(
            principal: 100_000_000,
            annualRate: 35,
            months: 1,
            startDate: new DateTimeImmutable('2026-03-31'),
            emi: 10_000_000,
            collectionFee: 0,
            paymentDay: 30,
        );

        $this->assertSame(31, $may31[0]['days']);
        $this->assertSame(30, $apr30[0]['days']);
        $this->assertGreaterThan($apr30[0]['interest'], $may31[0]['interest']);
        $this->assertSame(0, ((int) $may31[0]['interest']) % 10);
    }

    #[Test]
    public function should_not_shift_due_date_for_weekends(): void
    {
        // 2026-02-15 is Sunday
        $rows = $this->calc->calculate(
            principal: 12_000_000,
            annualRate: 35,
            months: 1,
            startDate: new DateTimeImmutable('2026-01-15'),
            emi: 1_100_000,
            collectionFee: 11_000,
            paymentDay: 15,
        );

        $this->assertSame('2026-02-15', $rows[0]['date']->format('Y-m-d'));
        $this->assertFalse($rows[0]['is_adjusted']);
    }

    #[Test]
    public function should_subtract_collection_fee_then_interest_then_principal(): void
    {
        $rows = $this->calc->calculate(
            principal: 50_000_000,
            annualRate: 35,
            months: 12,
            startDate: new DateTimeImmutable('2026-01-05'),
            emi: 6_072_000,
            collectionFee: 11_000,
            paymentDay: 5,
        );

        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame(11_000.0, $first['fee']);
        $this->assertSame(
            (float) $first['payment'],
            round($first['principal'] + $first['interest'] + $first['fee'], 0),
        );
        // Non-last periods keep EMI total
        $this->assertSame(6_072_000.0, $first['payment']);
        $this->assertSame(0.0, (float) end($rows)['remaining_principal']);
    }

    #[Test]
    public function should_zero_remaining_on_last_period(): void
    {
        $rows = $this->calc->calculate(
            principal: 20_000_000,
            annualRate: 35,
            months: 6,
            startDate: new DateTimeImmutable('2026-01-10'),
            emi: 4_000_000,
            collectionFee: 11_000,
            paymentDay: 10,
        );

        $this->assertSame(0.0, (float) end($rows)['remaining_principal']);
        foreach ($rows as $row) {
            $this->assertSame(0, ((int) $row['interest']) % 10);
        }
    }

    #[Test]
    public function should_recalculate_principal_when_interest_or_fee_overridden(): void
    {
        $base = $this->calc->calculate(
            principal: 10_000_000,
            annualRate: 35,
            months: 3,
            startDate: new DateTimeImmutable('2026-01-05'),
            emi: 4_000_000,
            collectionFee: 11_000,
            paymentDay: 5,
        );

        $overrides = array_map(static fn (array $r) => [
            'month_index' => $r['month_index'],
            'due_date' => $r['date']->format('Y-m-d'),
            'days' => $r['days'],
            'interest' => $r['interest'],
            'fee' => $r['fee'],
            'payment' => 4_000_000,
        ], $base);

        $overrides[0]['interest'] = $overrides[0]['interest'] + 100; // bump interest → less principal

        $rebuilt = $this->calc->recalculateFromOverrides(10_000_000, $overrides, 4_000_000);

        $this->assertLessThan($base[0]['principal'], $rebuilt[0]['principal']);
        $this->assertSame(0.0, (float) end($rebuilt)['remaining_principal']);
        $this->assertSame(
            (float) $rebuilt[0]['payment'],
            round($rebuilt[0]['principal'] + $rebuilt[0]['interest'] + $rebuilt[0]['fee'], 0),
        );
    }

    #[Test]
    public function should_compute_early_settlement_with_penalty(): void
    {
        $result = $this->calc->earlySettlement(
            remainingPrincipal: 140_000_000,
            annualRate: 35,
            lastPaymentDate: new DateTimeImmutable('2026-05-05'),
            settlementDate: new DateTimeImmutable('2026-05-20'),
            penaltyRate: 0.05,
        );

        $this->assertSame(15, $result['days']);
        $this->assertSame(7_000_000.0, $result['penalty']); // 5% of 140M
        $this->assertSame(0, ((int) $result['accrued_interest']) % 10);
        $this->assertSame(
            $result['total'],
            $result['remaining_principal'] + $result['accrued_interest'] + $result['penalty'],
        );
    }
}
