<?php

namespace Tests\Feature\Lending;

use App\Models\Holiday;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\AmortizationService;
use Wallets\Lending\Application\LoanPaymentScheduleService;
use Wallets\Lending\Application\LoanScheduleGenerator;

class LoanPaymentScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function daily_loan_period_due_date_uses_payment_day_and_holidays_for_past_periods(): void
    {
        Carbon::setTestNow('2026-07-15');

        Holiday::create([
            'date' => '2023-04-05',
            'name' => 'Giỗ Tổ Hùng Vương',
            'type' => 'public',
        ]);

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'Retro loan',
            'started_at' => '2021-07-21',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 60,
            'principal_amount' => 500000000,
            'interest_rate' => 10,
            'monthly_payment' => 10000000,
        ]);

        app(LoanScheduleGenerator::class)->generate($loan, backfillPast: false);

        $april2023Period = $loan->customSchedules()->where('month_index', 21)->first();
        $this->assertNotNull($april2023Period);
        $this->assertSame('2023-04-06', $april2023Period->due_date->toDateString());
    }

    #[Test]
    public function period_due_date_adjusts_using_all_holidays_in_database_not_only_future_ones(): void
    {
        Holiday::create([
            'date' => '2023-04-05',
            'name' => 'Giỗ Tổ Hùng Vương',
            'type' => 'public',
        ]);

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'Retro loan',
            'started_at' => '2021-07-21',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 60,
            'principal_amount' => 500000000,
            'interest_rate' => 10,
            'monthly_payment' => 10000000,
        ]);

        $schedule = app(AmortizationService::class)->calculate(
            $loan->id,
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->term_months,
            $loan->started_at,
            $loan->monthly_payment,
            'daily',
            $loan->payment_day,
        );

        $row = $schedule->firstWhere('month_index', 21);
        $due = app(LoanPaymentScheduleService::class)->periodDueDate($loan, $row);

        $this->assertSame('2023-04-06', $due->toDateString());
    }

    #[Test]
    public function remaining_principal_uses_last_paid_period_not_next_open_period(): void
    {
        Carbon::setTestNow('2026-07-27');

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'test',
            'started_at' => '2023-07-05',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 60,
            'principal_amount' => 300000000,
            'interest_rate' => 15.80,
            'monthly_payment' => 7263576,
            'months_paid' => 36,
        ]);

        app(LoanScheduleGenerator::class)->generate($loan, backfillPast: false);

        $period36 = $loan->customSchedules()->where('month_index', 36)->first();
        $period37 = $loan->customSchedules()->where('month_index', 37)->first();
        $period36->update(['status' => 'paid']);
        for ($i = 1; $i < 36; $i++) {
            $loan->customSchedules()->where('month_index', $i)->update(['status' => 'paid']);
        }

        $schedule = app(AmortizationService::class)->calculate(
            $loan->id,
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->term_months,
            $loan->started_at,
            $loan->monthly_payment,
            'daily',
            $loan->payment_day,
        );

        $service = app(LoanPaymentScheduleService::class);
        $monthsPassed = $service->effectiveMonthsPaid($loan, $schedule, collect());
        $remaining = $service->remainingPrincipalAt($loan, $schedule, $monthsPassed);

        $this->assertSame(36, $monthsPassed);
        $this->assertSame((float) $period36->remaining_principal, $remaining);
        $this->assertNotSame((float) $period37->remaining_principal, $remaining);
    }
}
