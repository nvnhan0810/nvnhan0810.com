<?php

namespace Tests\Feature\Reporting;

use App\Models\Loan;
use App\Models\RecurringItem;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixedExpenseSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function summary_groups_recurring_and_loan_dues_by_month_buckets(): void
    {
        Carbon::setTestNow('2026-08-15');

        $user = User::factory()->create(['email' => 'fixed@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 10_000_000,
            'is_active' => true,
        ]);

        RecurringItem::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'name' => 'Tiền nhà',
            'type' => 'expense',
            'amount' => 5_000_000,
            'day_of_month' => 5,
            'effective_from' => '2026-01-01',
            'ends_at' => null,
            'is_active' => true,
        ]);

        Loan::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'borrow',
            'name' => 'Bạn A',
            'principal_amount' => 6_000_000,
            'started_at' => '2026-01-10',
            'monthly_payment' => 1_100_000,
            'payment_day' => 15,
            'is_settled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('fixed-expenses.index', [
            'granularity' => 'month',
            'duration' => 2,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FixedExpenses/Summary', false)
            ->where('granularity', 'month')
            ->where('duration', 2)
            ->has('periods', 2)
            ->where('periods.0.items.0.name', 'Tiền nhà')
            ->where('periods.0.items.0.amount', 5_000_000)
            ->where('periods.0.total', 6_100_000)
        );
    }

    #[Test]
    public function settled_loan_and_inactive_recurring_are_excluded(): void
    {
        Carbon::setTestNow('2026-08-15');

        $user = User::factory()->create(['email' => 'fixed2@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1_000_000,
            'is_active' => true,
        ]);

        RecurringItem::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'name' => 'Off',
            'type' => 'expense',
            'amount' => 100_000,
            'day_of_month' => 1,
            'is_active' => false,
        ]);

        Loan::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'borrow',
            'name' => 'Settled',
            'principal_amount' => 1_000_000,
            'started_at' => '2026-01-01',
            'monthly_payment' => 500_000,
            'payment_day' => 10,
            'is_settled' => true,
        ]);

        $response = $this->actingAs($user)->get(route('fixed-expenses.index', [
            'granularity' => 'month',
            'duration' => 1,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('periods.0.items', [])
            ->where('grand_total', 0)
        );
    }
}
