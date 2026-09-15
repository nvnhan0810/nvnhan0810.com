<?php

namespace Tests\Unit\Reporting;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wallets\Reporting\Domain\FixedExpenseOccurrenceProjector;
use Wallets\Reporting\Domain\FixedExpenseSource;

class FixedExpenseOccurrenceProjectorTest extends TestCase
{
    #[Test]
    public function projects_recurring_expense_when_due_falls_in_window(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [[
                'id' => 1,
                'name' => 'Tiền nhà',
                'amount' => 5_000_000,
                'day_of_month' => 5,
                'effective_from' => '2026-01-01',
                'ends_at' => null,
                'is_active' => true,
            ]],
            loans: [],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-08-31'),
        );

        $this->assertCount(1, $events);
        $this->assertSame(FixedExpenseSource::RECURRING, $events[0]['source']);
        $this->assertSame('2026-08-05', $events[0]['due_date']);
        $this->assertSame(5_000_000.0, $events[0]['amount']);
    }

    #[Test]
    public function skips_recurring_outside_effective_window(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [[
                'id' => 2,
                'name' => 'Netflix',
                'amount' => 200_000,
                'day_of_month' => 10,
                'effective_from' => '2026-09-01',
                'ends_at' => null,
                'is_active' => true,
            ]],
            loans: [],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-08-31'),
        );

        $this->assertCount(0, $events);
    }

    #[Test]
    public function includes_recurring_on_end_month_when_ends_at_set(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [[
                'id' => 3,
                'name' => 'Gym',
                'amount' => 500_000,
                'day_of_month' => 20,
                'effective_from' => null,
                'ends_at' => '2026-08-31',
                'is_active' => true,
            ]],
            loans: [],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-09-30'),
        );

        $this->assertCount(1, $events);
        $this->assertSame('2026-08-20', $events[0]['due_date']);
    }

    #[Test]
    public function projects_bank_loan_schedule_rows_while_not_settled(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [],
            loans: [[
                'id' => 10,
                'name' => 'ACB',
                'type' => 'bank',
                'is_settled' => false,
                'monthly_payment' => 1_100_000,
                'payment_day' => 15,
                'started_at' => '2026-01-15',
                'term_months' => 12,
                'schedules' => [
                    ['due_date' => '2026-08-15', 'payment' => 1_100_000, 'status' => 'pending'],
                    ['due_date' => '2026-09-15', 'payment' => 1_100_000, 'status' => 'pending'],
                ],
            ]],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-08-31'),
        );

        $this->assertCount(1, $events);
        $this->assertSame(FixedExpenseSource::LOAN, $events[0]['source']);
        $this->assertSame('2026-08-15', $events[0]['due_date']);
    }

    #[Test]
    public function skips_settled_loans(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [],
            loans: [[
                'id' => 11,
                'name' => 'Done',
                'type' => 'bank',
                'is_settled' => true,
                'monthly_payment' => 1_000_000,
                'payment_day' => 1,
                'started_at' => '2026-01-01',
                'term_months' => 6,
                'schedules' => [
                    ['due_date' => '2026-08-01', 'payment' => 1_000_000, 'status' => 'pending'],
                ],
            ]],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-08-31'),
        );

        $this->assertCount(0, $events);
    }

    #[Test]
    public function projects_borrow_monthly_payment_until_settled(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [],
            loans: [[
                'id' => 12,
                'name' => 'Bạn A',
                'type' => 'borrow',
                'is_settled' => false,
                'monthly_payment' => 2_000_000,
                'payment_day' => 10,
                'started_at' => '2026-06-10',
                'term_months' => null,
                'schedules' => [],
            ]],
            rangeStart: new DateTimeImmutable('2026-08-01'),
            rangeEnd: new DateTimeImmutable('2026-09-30'),
        );

        $this->assertCount(2, $events);
        $this->assertSame('2026-08-10', $events[0]['due_date']);
        $this->assertSame('2026-09-10', $events[1]['due_date']);
    }

    #[Test]
    public function clamps_day_of_month_to_short_months(): void
    {
        $projector = new FixedExpenseOccurrenceProjector;
        $events = $projector->project(
            recurring: [[
                'id' => 4,
                'name' => 'Cuối tháng',
                'amount' => 100_000,
                'day_of_month' => 31,
                'effective_from' => null,
                'ends_at' => null,
                'is_active' => true,
            ]],
            loans: [],
            rangeStart: new DateTimeImmutable('2026-02-01'),
            rangeEnd: new DateTimeImmutable('2026-02-28'),
        );

        $this->assertCount(1, $events);
        $this->assertSame('2026-02-28', $events[0]['due_date']);
    }
}
