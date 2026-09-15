<?php

namespace Tests\Feature\Lending;

use App\Models\LoanCustomSchedule;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\Lending\Application\Command\RecordLoanPayment;
use Wallets\Shared\Application\CommandBus;

class LoanScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeWallet(User $user, float $balance = 0): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function createBankLoan(User $user, string $startedAt, ?int $walletId = null, bool $recordCashFlow = false, ?float $received = null): array
    {
        return app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'bank',
                'name' => 'ACB',
                'principal_amount' => 12000000,
                'started_at' => $startedAt,
                'wallet_id' => $walletId,
                'interest_rate' => 12,
                'interest_calculation_method' => 'monthly',
                'term_months' => 12,
                'monthly_payment' => 1100000,
                'payment_day' => 15,
            ],
            recordCashFlow: $recordCashFlow,
            receivedAmount: $received,
        ));
    }

    #[Test]
    public function bank_loan_started_today_generates_pending_periods_and_records_received_amount(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $result = $this->createBankLoan($user, '2026-07-15', $wallet->id, recordCashFlow: true, received: 11800000);
        $loan = $result['loan'];

        $this->assertSame(12, LoanCustomSchedule::where('loan_id', $loan->id)->count());
        $this->assertSame(12, LoanCustomSchedule::where('loan_id', $loan->id)->where('status', 'pending')->count());
        // Số thực nhận (khác gốc) được ghi vào ví.
        $this->assertEquals(11800000, (float) $wallet->fresh()->balance);
    }

    #[Test]
    public function retroactive_bank_loan_marks_past_periods_paid_without_wallet_transaction(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);

        // Bắt đầu 6 tháng trước; kỳ tới hạn <= hôm nay được đánh dấu đã trả.
        $result = $this->createBankLoan($user, '2026-01-15', $wallet->id, recordCashFlow: true, received: 12000000);
        $loan = $result['loan'];

        $paid = LoanCustomSchedule::where('loan_id', $loan->id)->where('status', 'paid')->count();
        $this->assertSame(6, $paid); // 02..07 (2026-07-15 == due tháng 6)
        $this->assertSame(6, (int) $loan->fresh()->months_paid);
        // Không phải hôm nay → không ghi ví.
        $this->assertEquals(0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('transactions', ['loan_id' => $loan->id]);
    }

    #[Test]
    public function recording_payment_marks_earliest_open_period_paid_and_updates_wallet(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user, 5000000);

        $loan = $this->createBankLoan($user, '2026-07-15', $wallet->id)['loan'];

        app(CommandBus::class)->dispatch(new RecordLoanPayment(
            userId: $user->id,
            data: [
                'loan_id' => $loan->id,
                'wallet_id' => $wallet->id,
                'amount' => 1100000,
                'paid_at' => '2026-08-15',
            ],
        ));

        $firstPeriod = LoanCustomSchedule::where('loan_id', $loan->id)->orderBy('month_index')->first();
        $this->assertSame('paid', $firstPeriod->status);
        $this->assertNotNull($firstPeriod->payment_id);
        $this->assertEquals(3900000, (float) $wallet->fresh()->balance);
    }

    #[Test]
    public function advancing_calendar_marks_newly_past_due_periods_paid_on_detail(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $loan = $this->createBankLoan($user, '2026-07-15', $wallet->id)['loan'];

        $this->assertSame(0, LoanCustomSchedule::where('loan_id', $loan->id)->where('status', 'paid')->count());

        // Ngày đến hạn kỳ 1 = 15/08; sau ngày đó mới tự đánh dấu đã trả.
        Carbon::setTestNow('2026-08-16');

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertOk();

        $paid = LoanCustomSchedule::where('loan_id', $loan->id)->where('status', 'paid')->count();
        $this->assertSame(1, $paid);
        $this->assertSame(1, (int) $loan->fresh()->months_paid);
    }

    #[Test]
    public function loan_detail_timeline_exposes_dates_as_strings_not_objects(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);
        $loan = $this->createBankLoan($user, '2026-07-15', $wallet->id)['loan'];

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Loans/Show', false)
                ->has('timeline.0', fn (AssertableInertia $row) => $row
                    ->where('type', 'period')
                    ->where('period_due_date', fn ($v) => is_string($v) && $v !== '')
                    ->where('period.date', fn ($v) => is_string($v) && $v !== '')
                    ->etc()
                )
            );
    }
}
