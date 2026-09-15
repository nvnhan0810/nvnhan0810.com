<?php

namespace Tests\Feature\Lending;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeCreditLoanPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preview_renders_homecredit_schedule_for_bank_loan(): void
    {
        $user = User::factory()->create(['email' => 'hc-preview@example.com']);

        $response = $this->actingAs($user)->post(route('loans.preview'), [
            'type' => 'bank',
            'name' => 'Home Credit',
            'principal_amount' => 50_000_000,
            'started_at' => '05/01/2026',
            'interest_rate' => 35,
            'interest_calculation_method' => 'homecredit',
            'term_months' => 12,
            'months_paid' => 0,
            'monthly_payment' => 6_072_000,
            'collection_fee' => 11_000,
            'payment_day' => 5,
            'record_cash_flow' => false,
        ]);

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Loans/Preview', false)
            ->where('loan.interest_calculation_method', 'homecredit')
            ->where('loan.collection_fee', 11_000)
            ->has('schedule', 12)
            ->where('schedule.0.fee', 11_000)
            ->where('schedule.11.remaining_principal', 0)
        );
    }

    #[Test]
    public function store_persists_edited_homecredit_schedule(): void
    {
        $user = User::factory()->create(['email' => 'hc-store@example.com']);
        Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);

        $schedule = [
            [
                'month_index' => 1,
                'paid_at' => '2026-02-05',
                'payment' => 6_072_000,
                'principal' => 1_000_000,
                'interest' => 5_061_000,
                'fee' => 11_000,
                'remaining_principal' => 49_000_000,
            ],
            [
                'month_index' => 2,
                'paid_at' => '2026-03-05',
                'payment' => 49_011_000,
                'principal' => 49_000_000,
                'interest' => 0,
                'fee' => 11_000,
                'remaining_principal' => 0,
            ],
        ];

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'type' => 'bank',
            'name' => 'Home Credit',
            'principal_amount' => 50_000_000,
            'started_at' => '05/01/2026',
            'interest_rate' => 35,
            'interest_calculation_method' => 'homecredit',
            'term_months' => 2,
            'months_paid' => 0,
            'monthly_payment' => 6_072_000,
            'collection_fee' => 11_000,
            'payment_day' => 5,
            'record_cash_flow' => false,
            'custom_schedule' => $schedule,
        ]);

        $response->assertRedirect(route('loans.index'));

        $loan = Loan::query()->where('name', 'Home Credit')->first();
        $this->assertNotNull($loan);
        $this->assertSame('homecredit', $loan->interest_calculation_method);
        $this->assertEquals(11_000, (float) $loan->collection_fee);

        $this->assertDatabaseHas('loan_custom_schedules', [
            'loan_id' => $loan->id,
            'month_index' => 1,
            'interest' => 5_061_000,
            'fee' => 11_000,
            'principal' => 1_000_000,
        ]);
        $this->assertSame(2, LoanCustomSchedule::where('loan_id', $loan->id)->count());
    }
}
