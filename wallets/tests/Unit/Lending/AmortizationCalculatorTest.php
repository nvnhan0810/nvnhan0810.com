<?php

namespace Tests\Unit\Lending;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wallets\Lending\Domain\AmortizationCalculator;

/**
 * Domain thuần: test không dựng app Laravel (extend PHPUnit TestCase trực tiếp).
 */
class AmortizationCalculatorTest extends TestCase
{
    #[Test]
    public function monthly_schedule_amortizes_to_zero_balance(): void
    {
        $calc = new AmortizationCalculator;

        $rows = $calc->calculate(
            principal: 12000000,
            annualRate: 12,
            months: 12,
            startDate: new DateTimeImmutable('2026-01-15'),
            fixedMonthlyPayment: 1100000,
            method: 'monthly',
        );

        $this->assertCount(12, $rows);
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[0]['date']);
        $this->assertSame('2026-02-15', $rows[0]['date']->format('Y-m-d'));
        $this->assertSame(0.0, (float) end($rows)['remaining_principal']);
    }

    #[Test]
    public function daily_method_pushes_due_date_off_weekends_and_holidays(): void
    {
        $calc = new AmortizationCalculator;

        // 2026-02-15 rơi Chủ nhật -> phải đẩy sang ngày làm việc kế tiếp.
        $rows = $calc->calculate(
            principal: 12000000,
            annualRate: 12,
            months: 1,
            startDate: new DateTimeImmutable('2026-01-15'),
            fixedMonthlyPayment: 1100000,
            method: 'daily',
            holidays: ['2026-02-16'],
        );

        // 15 (CN) -> 16 (nghỉ lễ) -> 17 (thứ Ba).
        $this->assertSame('2026-02-17', $rows[0]['date']->format('Y-m-d'));
        $this->assertTrue($rows[0]['is_adjusted']);
    }

    #[Test]
    public function custom_schedule_uses_provided_rows(): void
    {
        $calc = new AmortizationCalculator;

        $rows = $calc->calculate(
            principal: 1000000,
            annualRate: 0,
            months: 0,
            startDate: new DateTimeImmutable('2026-01-15'),
            method: 'custom',
            customRows: [
                ['month_index' => 1, 'due_date' => '2026-02-15', 'payment' => 600000, 'principal' => 500000, 'interest' => 100000, 'fee' => 0],
                ['month_index' => 2, 'due_date' => '2026-03-15', 'payment' => 600000, 'principal' => 500000, 'interest' => 100000, 'fee' => 0],
            ],
        );

        $this->assertCount(2, $rows);
        $this->assertSame(500000.0, (float) $rows[0]['remaining_principal']);
        $this->assertSame(0.0, (float) $rows[1]['remaining_principal']);
    }
}
