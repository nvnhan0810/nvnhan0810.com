<?php

namespace Tests\Feature\Lending;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\LoanScheduleStateService;

class LoanReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoanWithPeriod(User $user, string $dueDate, string $status = 'pending'): LoanCustomSchedule
    {
        $loan = Loan::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'name' => 'ACB',
            'principal_amount' => 12000000,
            'started_at' => '2026-01-15',
            'term_months' => 12,
            'monthly_payment' => 1100000,
            'payment_day' => 15,
        ]);

        return LoanCustomSchedule::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'month_index' => 1,
            'due_date' => $dueDate,
            'payment' => 1100000,
            'principal' => 1000000,
            'interest' => 100000,
            'remaining_principal' => 11000000,
            'status' => $status,
        ]);
    }

    #[Test]
    public function transition_marks_due_and_overdue(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = User::factory()->create();

        $dueToday = $this->makeLoanWithPeriod($user, '2026-07-15');
        $overdue = $this->makeLoanWithPeriod($user, '2026-07-10');
        $future = $this->makeLoanWithPeriod($user, '2026-08-15');

        app(LoanScheduleStateService::class)->transitionStatuses($user->id);

        $this->assertSame('due', $dueToday->fresh()->status);
        $this->assertSame('overdue', $overdue->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
    }

    #[Test]
    public function process_command_sends_telegram_for_due_period_and_marks_reminded(): void
    {
        Carbon::setTestNow('2026-07-15');
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        Setting::setForUser($user->id, 'telegram_enabled', '1');
        Setting::setForUser($user->id, 'telegram_chat_id', '123456');

        $period = $this->makeLoanWithPeriod($user, '2026-07-15', 'due');

        $this->artisan('loans:process-periods')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && str_contains($request->url(), 'sendMessage'));
        $this->assertNotNull($period->fresh()->reminded_telegram_at);
    }

    #[Test]
    public function process_command_skips_when_telegram_disabled(): void
    {
        Carbon::setTestNow('2026-07-15');
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake();

        $user = User::factory()->create();
        $period = $this->makeLoanWithPeriod($user, '2026-07-15', 'due');

        $this->artisan('loans:process-periods')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($period->fresh()->reminded_telegram_at);
    }
}
