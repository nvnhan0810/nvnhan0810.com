<?php

namespace Tests\Feature\Lending;

use App\Models\Holiday;
use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\AmortizationService;
use Wallets\Lending\Application\LoanPaymentScheduleService;
use Wallets\Lending\Application\LoanScheduleGenerator;

class ShinhanDailyLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (require database_path('data/vietnam_public_holidays.php') as $holiday) {
            Holiday::create($holiday);
        }
    }

    #[Test]
    public function shinhan_demo_loan_matches_bank_first_period_and_august_2023_due_date(): void
    {
        Carbon::setTestNow('2026-07-27');

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'Shinhan',
            'started_at' => '2021-07-05',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 60,
            'principal_amount' => 300000000,
            'interest_rate' => 15.80,
            'monthly_payment' => 7263576,
        ]);

        app(LoanScheduleGenerator::class)->generate($loan, backfillPast: false);

        $firstPeriod = $loan->customSchedules()->where('month_index', 1)->first();
        $august2023Period = $loan->customSchedules()->where('month_index', 25)->first();

        $this->assertSame('2021-08-07', $firstPeriod->due_date->toDateString());
        $this->assertSame(4285479.0, (float) $firstPeriod->interest);
        $this->assertSame(2978097.0, (float) $firstPeriod->principal);

        $this->assertSame('2023-08-07', $august2023Period->due_date->toDateString());

        $schedule = app(AmortizationService::class)->calculate(
            $loan->id,
            $loan->principal_amount,
            $loan->interest_rate,
            25,
            $loan->started_at,
            $loan->monthly_payment,
            'daily',
            $loan->payment_day,
        );
        $row25 = $schedule->firstWhere('month_index', 25);
        $this->assertSame('2023-08-07', $row25['date']->format('Y-m-d'));
        $this->assertSame(33, $row25['days']);
    }

    #[Test]
    public function effective_months_paid_uses_persisted_paid_schedules(): void
    {
        Carbon::setTestNow('2026-07-27');

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'Shinhan',
            'started_at' => '2021-07-05',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 60,
            'principal_amount' => 300000000,
            'interest_rate' => 15.80,
            'monthly_payment' => 7263576,
            'months_paid' => 60,
        ]);

        app(LoanScheduleGenerator::class)->generate($loan, backfillPast: true);

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

        $monthsPassed = app(LoanPaymentScheduleService::class)->effectiveMonthsPaid($loan, $schedule, collect());

        $this->assertSame(60, $monthsPassed);
    }

    #[Test]
    public function effective_months_paid_advances_when_due_dates_pass_without_wallet_payment(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = User::factory()->create();
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'Shinhan',
            'started_at' => '2026-07-05',
            'interest_calculation_method' => 'daily',
            'payment_day' => 5,
            'term_months' => 6,
            'principal_amount' => 300000000,
            'interest_rate' => 15.80,
            'monthly_payment' => 7263576,
            'months_paid' => 0,
        ]);

        app(LoanScheduleGenerator::class)->generate($loan, backfillPast: false);

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

        $this->assertSame(0, app(LoanPaymentScheduleService::class)->effectiveMonthsPaid($loan->fresh(), $schedule, collect()));

        // Kỳ 1 daily thường đến hạn ~07–08; sang tháng 9 chắc chắn đã qua ≥ 1 kỳ.
        Carbon::setTestNow('2026-09-10');
        app(LoanScheduleGenerator::class)->backfillPastPeriods($loan->fresh());

        $monthsPassed = app(LoanPaymentScheduleService::class)->effectiveMonthsPaid($loan->fresh(), $schedule, collect());
        $this->assertGreaterThanOrEqual(1, $monthsPassed);
        $this->assertSame(
            LoanCustomSchedule::where('loan_id', $loan->id)->where('status', 'paid')->max('month_index'),
            $monthsPassed
        );
    }
}
