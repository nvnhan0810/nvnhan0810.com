<?php

namespace Tests\Feature\Reporting;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_renders_upcoming_loan_reminders_as_arrays(): void
    {
        Carbon::setTestNow('2026-07-15');

        $user = User::factory()->create(['email' => 'dashboard@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 5_000_000,
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'bank',
            'name' => 'ACB',
            'principal_amount' => 12_000_000,
            'started_at' => '2026-01-15',
            'term_months' => 12,
            'monthly_payment' => 1_100_000,
            'payment_day' => 15,
            'is_settled' => false,
        ]);

        LoanCustomSchedule::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'month_index' => 1,
            'due_date' => '2026-07-16',
            'payment' => 1_100_000,
            'principal' => 1_000_000,
            'interest' => 100_000,
            'remaining_principal' => 11_000_000,
            'status' => LoanCustomSchedule::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index', false)
            ->has('upcomingReminders', 1)
            ->where('upcomingReminders.0.kind', 'loan')
            ->where('upcomingReminders.0.name', 'ACB')
            ->where('upcomingReminders.0.amount', 1_100_000)
            ->where('upcomingReminders.0.wallet_name', 'Cash')
            ->has('upcomingReminders.0.pay_url')
        );
    }
}
