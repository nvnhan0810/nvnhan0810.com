<?php

namespace Tests\Feature\RecurringPlanning;

use App\Models\Loan;
use App\Models\RecurringItem;
use App\Models\RecurringOccurrence;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceGenerator;
use Wallets\Shared\Application\CommandBus;

class RecurringReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeDueOccurrence(User $user): RecurringOccurrence
    {
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000,
            'is_active' => true,
        ]);
        $item = RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Internet',
            'type' => 'expense',
            'amount' => 200000,
            'wallet_id' => $wallet->id,
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-07-31'));

        return RecurringOccurrence::where('recurring_item_id', $item->id)->firstOrFail();
    }

    #[Test]
    public function process_reminders_sends_telegram_for_due_recurring_and_marks_reminded(): void
    {
        Carbon::setTestNow('2026-07-15');
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        Setting::setForUser($user->id, 'telegram_enabled', '1');
        Setting::setForUser($user->id, 'telegram_chat_id', '123456');

        $occ = $this->makeDueOccurrence($user);

        $this->artisan('finance:process-reminders')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && str_contains($request->url(), 'sendMessage'));
        $this->assertNotNull($occ->fresh()->reminded_telegram_at);
    }

    #[Test]
    public function process_reminders_skips_when_telegram_disabled(): void
    {
        Carbon::setTestNow('2026-07-15');
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake();

        $user = User::factory()->create();
        $occ = $this->makeDueOccurrence($user);

        $this->artisan('finance:process-reminders')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($occ->fresh()->reminded_telegram_at);
    }

    #[Test]
    public function creating_bank_loan_no_longer_creates_recurring_item(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);

        app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'bank',
                'name' => 'ACB',
                'principal_amount' => 12000000,
                'started_at' => '2026-07-15',
                'wallet_id' => $wallet->id,
                'interest_rate' => 12,
                'interest_calculation_method' => 'monthly',
                'term_months' => 12,
                'monthly_payment' => 1100000,
                'payment_day' => 15,
            ],
            recordCashFlow: false,
        ));

        $this->assertDatabaseCount('recurring_items', 0);
        $this->assertGreaterThan(0, Loan::count());
    }
}
