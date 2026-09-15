<?php

namespace Tests\Feature\Lending;

use App\Models\Holiday;
use App\Models\LoanCustomSchedule;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Calendar\Application\Command\CreateHoliday;
use Wallets\Calendar\Application\Command\DeleteHoliday;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\Lending\Application\LoanScheduleRecalculator;
use Wallets\Shared\Application\CommandBus;

class LoanScheduleRecalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function adding_holiday_recalculates_open_periods_for_daily_bank_loans(): void
    {
        Carbon::setTestNow('2026-01-10');

        $user = User::factory()->create(['email' => 'daily-loan@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);

        $result = app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'bank',
                'name' => 'Daily loan',
                'principal_amount' => 12000000,
                'started_at' => '2026-01-01',
                'wallet_id' => $wallet->id,
                'interest_rate' => 12,
                'interest_calculation_method' => 'daily',
                'term_months' => 12,
                'monthly_payment' => 1100000,
                'payment_day' => 15,
            ],
            recordCashFlow: false,
        ));

        $loan = $result['loan'];
        $firstPeriod = LoanCustomSchedule::where('loan_id', $loan->id)->orderBy('month_index')->first();
        $beforeDue = $firstPeriod->due_date->toDateString();
        $beforeInterest = (float) $firstPeriod->interest;

        app(CommandBus::class)->dispatch(new CreateHoliday(data: [
            'date' => '2026-02-16',
            'name' => 'Tết Nguyên Đán',
            'type' => 'public',
        ]));

        $firstPeriod->refresh();

        $this->assertNotSame($beforeDue, $firstPeriod->due_date->toDateString());
        $this->assertNotSame($beforeInterest, (float) $firstPeriod->interest);
    }

    #[Test]
    public function settled_daily_loans_are_not_recalculated(): void
    {
        Carbon::setTestNow('2026-01-10');

        $user = User::factory()->create(['email' => 'settled-loan@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);

        $result = app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'bank',
                'name' => 'Settled daily loan',
                'principal_amount' => 12000000,
                'started_at' => '2026-01-15',
                'wallet_id' => $wallet->id,
                'interest_rate' => 12,
                'interest_calculation_method' => 'daily',
                'term_months' => 12,
                'monthly_payment' => 1100000,
                'payment_day' => 15,
            ],
            recordCashFlow: false,
        ));

        $loan = $result['loan'];
        $loan->update(['is_settled' => true]);

        $firstPeriod = LoanCustomSchedule::where('loan_id', $loan->id)->orderBy('month_index')->first();
        $beforeDue = $firstPeriod->due_date->toDateString();

        Holiday::create([
            'date' => '2026-02-16',
            'name' => 'Tết Nguyên Đán',
            'type' => 'public',
        ]);

        app(LoanScheduleRecalculator::class)->recalculateUnsettledDailyLoans();

        $this->assertSame($beforeDue, $firstPeriod->fresh()->due_date->toDateString());
    }

    #[Test]
    public function paid_periods_keep_their_due_dates_when_holiday_is_removed(): void
    {
        Carbon::setTestNow('2026-07-15');

        $user = User::factory()->create(['email' => 'paid-period@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 5000000,
            'is_active' => true,
        ]);

        $result = app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'bank',
                'name' => 'Daily loan paid',
                'principal_amount' => 12000000,
                'started_at' => '2026-07-15',
                'wallet_id' => $wallet->id,
                'interest_rate' => 12,
                'interest_calculation_method' => 'daily',
                'term_months' => 12,
                'monthly_payment' => 1100000,
                'payment_day' => 15,
            ],
            recordCashFlow: false,
        ));

        $loan = $result['loan'];
        $paidPeriod = LoanCustomSchedule::where('loan_id', $loan->id)->orderBy('month_index')->first();
        $paidPeriod->update(['status' => LoanCustomSchedule::STATUS_PAID]);
        $paidDue = $paidPeriod->due_date->toDateString();

        $holiday = Holiday::create([
            'date' => '2026-08-16',
            'name' => 'Test holiday',
            'type' => 'public',
        ]);

        app(CommandBus::class)->dispatch(new DeleteHoliday(holidayId: $holiday->id));

        $this->assertSame($paidDue, $paidPeriod->fresh()->due_date->toDateString());
        $this->assertSame(LoanCustomSchedule::STATUS_PAID, $paidPeriod->fresh()->status);
    }
}
